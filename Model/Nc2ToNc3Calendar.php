<?php
/**
 * Nc2ToNc3Calendar
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');
App::uses('CalendarPermissiveRooms', 'Calendars.Utility');
App::uses('WorkflowComponent', 'Workflow.Controller/Component');
App::uses('ComponentCollection', 'Controller');

/**
 * Nc2ToNc3Calendar
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 * @method string getLanguageIdFromNc2()
 * @method string convertDate($date)
 * @method string convertLanguage($langDirName)
 * @method array saveMap($modelName, $idMap)
 * @method array getMap($nc2Id)
 * @method void changeNc3CurrentLanguage($langDirName = null)
 * @method void restoreNc3CurrentLanguage()
 * @method string convertChoiceValue($nc2Value, $nc3Choices)
 * @method string convertTitleIcon($titleIcon)
 * @method string convertTimezone($timezoneOffset)
 *
 * @see Nc2ToNc3CalendarBehavior
 * @method string getLogArgument($nc2Calendar)
 * @method array generateNc3CalendarPermissionData($nc2CalendarManage)
 * @method array generateNc3CalendarFrameSettingData($nc2CalendarBlock)
 * @method array generateNc3CalendarActionPlanData($nc2CalendarPlan)
 *
 */
class Nc2ToNc3Calendar extends Nc2ToNc3AppModel {

/**
 * Custom database table name, or null/false if no table association is desired.
 *
 * @var string
 * @link http://book.cakephp.org/2.0/en/models/model-attributes.html#usetable
 */
	public $useTable = false;

/**
 * List of behaviors to load when the model object is initialized. Settings can be
 * passed to behaviors by using the behavior name as index.
 *
 * @var array
 * @link http://book.cakephp.org/2.0/en/models/behaviors.html#using-behaviors
 */
	public $actsAs = [
		'Nc2ToNc3.Nc2ToNc3Calendar',
		'Nc2ToNc3.Nc2ToNc3Wysiwyg',
	];

/**
 * Migration method.
 *
 * @return bool True on success.
 * @throws Exception
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Calendar Migration start.'));

		/* @var $Nc2CalendarManage AppModel */
		$Nc2CalendarManage = $this->getNc2Model('calendar_manage');
		$nc2CalendarManages = $Nc2CalendarManage->find('all');
		if (!$this->__saveCalendarPermissionFromNc2($nc2CalendarManages)) {
			return false;
		}

		/* @var $Nc2CalendarBlock AppModel */
		$Nc2CalendarBlock = $this->getNc2Model('calendar_block');
		$nc2CalendarBlocks = $Nc2CalendarBlock->find('all');
		if (!$this->__saveCalendarFrameSettingFromNc2($nc2CalendarBlocks)) {
			return false;
		}

		// CalendarPermissionがCalendarのaliasで登録されるため、正しいModelが取得できなくなる。
		// ここで、一旦登録解除しとく。
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/CalendarPermission.php#L38
		ClassRegistry::removeObject('Calendar');

		// MailQueueUserがAppModelで登録されてしまってる場合があるので登録し直し。
		// @see https://github.com/NetCommons3/Mails/blob/3.1.0/Model/MailQueue.php#L94
		// @see https://github.com/NetCommons3/Mails/blob/3.1.0/Model/MailQueueUser.php#L96-L110
		ClassRegistry::removeObject('MailQueueUser');
		ClassRegistry::init('Mails.MailQueueUser');
		ClassRegistry::removeObject('MailQueue');
		ClassRegistry::init('Mails.MailQueue');

		/* @var $Nc2CalendarPlan AppModel */
		$Nc2CalendarPlan = $this->getNc2Model('calendar_plan');
		$query = [
			'recursive' => -1,
			//ブロックに配置されていなくても移行する
			/*
			'joins' => [
				[
					'type' => 'INNER',
					'alias' => 'Nc2CalendarBlock',
					'table' => 'calendar_block',
					'conditions' => 'Nc2CalendarPlan.room_id = Nc2CalendarBlock.room_id',
				],
				[
					'type' => 'INNER',
					'alias' => 'Nc2Block',
					'table' => 'blocks',
					'conditions' => 'Nc2CalendarBlock.block_id = Nc2Block.block_id',
				],
			],
			'conditions' => [
				'Nc2CalendarPlan.title !=' => "",
			],
			*/
			'order' => [
				'Nc2CalendarPlan.calendar_id',
			],
		];
		$nc2CalendarPlans = $Nc2CalendarPlan->find('all', $query);
		if (!$this->__saveCalendarEventFromNc2($nc2CalendarPlans)) {
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Calendar Migration end.'));
		return true;
	}

/**
 * Save CalendarPermission from Nc2.
 *
 * @param array $nc2CalendarManages Nc2CalendarManage data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveCalendarPermissionFromNc2($nc2CalendarManages) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  CalendarPermission data Migration start.'));

		/* @var $CalendarPermission CalendarPermission */
		$CalendarPermission = ClassRegistry::init('Calendars.CalendarPermission');
		foreach ($nc2CalendarManages as $nc2CalendarManage) {
			$CalendarPermission->begin();
			try {
				$data = $this->generateNc3CalendarPermissionData($nc2CalendarManage);
				if (!$data) {
					$CalendarPermission->rollback();
					continue;
				}

				if (!$CalendarPermission->savePermission($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2CalendarManage) . "\n" .
						var_export($CalendarPermission->validationErrors, true);
					$this->writeMigrationLog($message);

					$CalendarPermission->rollback();
					continue;
				}

				$nc2RoomId = $nc2CalendarManage['Nc2CalendarManage']['room_id'];
				$idMap = [
					$nc2RoomId => $CalendarPermission->id
				];
				$this->saveMap('CalendarPermission', $idMap);

				$CalendarPermission->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $CalendarFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$CalendarPermission->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', '  CalendarPermission data Migration end.'));

		return true;
	}

/**
 * Save CalendarFrameSetting from Nc2.
 *
 * @param array $nc2CalendarBlocks Nc2CalendarBlock data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveCalendarFrameSettingFromNc2($nc2CalendarBlocks) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  CalendarFrameSetting data Migration start.'));

		/* @var $CalendarFrameSetting CalendarFrameSetting */
		$CalendarFrameSetting = ClassRegistry::init('Calendars.CalendarFrameSetting');
		foreach ($nc2CalendarBlocks as $nc2CalendarBlock) {
			$CalendarFrameSetting->begin();
			try {
				$data = $this->generateNc3CalendarFrameSettingData($nc2CalendarBlock);
				if (!$data) {
					$CalendarFrameSetting->rollback();
					continue;
				}

				if (!$CalendarFrameSetting->saveFrameSetting($data)) {
					// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
					// var_exportは大丈夫らしい。。。
					// @see https://phpmd.org/rules/design.html
					$message = $this->getLogArgument($nc2CalendarBlock) . "\n" .
						var_export($CalendarFrameSetting->validationErrors, true);
					$this->writeMigrationLog($message);

					$CalendarFrameSetting->rollback();
					continue;
				}

				$nc2BlockId = $nc2CalendarBlock['Nc2CalendarBlock']['block_id'];
				$idMap = [
					$nc2BlockId => $CalendarFrameSetting->id
				];
				$this->saveMap('CalendarFrameSetting', $idMap);

				$CalendarFrameSetting->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $CalendarFrameSetting::savePage()でthrowされるとこの処理に入ってこない
				$CalendarFrameSetting->rollback($ex);
				throw $ex;
			}
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', '  CalendarFrameSetting data Migration end.'));

		return true;
	}

/**
 * Save CalendarEvent from Nc2.
 *
 * @param array $nc2CalendarPlans Nc2CalendarPlan data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveCalendarEventFromNc2($nc2CalendarPlans) {
		$this->writeMigrationLog(__d('nc2_to_nc3', '  CalendarEvent data Migration start.'));
		//暫定修正 本家が修正された場合に削除してかまいません
		if(count($nc2CalendarPlans) === 0){
			$this->writeMigrationLog(__d('nc2_to_nc3', '  CalendarEvent data Migration end.'));
			return true;
		}

		/* @var $CalendarEvent CalendarEvent */
		$CalendarEvent = ClassRegistry::init('Calendars.CalendarEvent');

		// CalendarPermissiveRooms.php#L195 でNotice Error: Undefined index が発生するための前処理
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Utility/CalendarPermissiveRooms.php#L195
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Controller/CalendarPlansController.php#L145-L147
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/CalendarEvent.php#L313
		CalendarPermissiveRooms::setRoomPermRoles($CalendarEvent->prepareCalRoleAndPerm());

		// @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Behavior/TopicsBaseBehavior.php#L347
		Current::write('Plugin.key', 'calendars');

		/* @var $CalendarActionPlan CalendarActionPlan */
		$CalendarActionPlan = ClassRegistry::init('Calendars.CalendarActionPlan');
		foreach ($nc2CalendarPlans as $nc2CalendarPlan) {
			$CalendarActionPlan->begin();
			try {
				$data = $this->generateNc3CalendarActionPlanData($nc2CalendarPlan);
				if (!$data) {
					$CalendarActionPlan->rollback();
					continue;
				}

				// Blockデータ作成処理が追加されたので呼び出す
				// https://github.com/NetCommons3/Calendars/commit/50d9fb10c624220778aab773186973b3bae1ecea
				$data = $CalendarActionPlan->Calendar->prepareBlockWithoutFrame($data);

				// 予定登録処理で使用しているデータをセット
				// データの有無しか使ってないっっぽいけど、一応予定のroom_idから取得したblock_idをセット
				// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/CalendarActionPlan.php#L545
				// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/Calendar.php#L153-L155
				// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/Behavior/CalendarInsertPlanBehavior.php#L97
				$nc3RoomId = $data['Block']['room_id'];
				Current::write('Frame.block_id', $data['Block']['id']);
				Current::write('Frame.room_id', $nc3RoomId);
				Current::write('Frame.plugin_key', 'calendars');
				Current::write('Room.id', $nc3RoomId);

				// Validation で throw されるため、事前にチェック
				$validationData = $CalendarActionPlan->convertToPlanParamFormat($data);
				if (!$CalendarEvent->checkMaxMinDate([$validationData['start_date']], 'start') ||
					!$CalendarEvent->checkMaxMinDate([$validationData['end_date']], 'end')
				) {
					$message = __d('nc2_to_nc3', '%s does not migration.', $this->getLogArgument($nc2CalendarPlan));
					$this->writeMigrationLog($message);

					$CalendarActionPlan->rollback();
					continue;
				}

				// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/CalendarEvent.php#L313
				// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Utility/CalendarPermissiveRooms.php#L202-L203
				$creatableValue = CalendarPermissiveRooms::$roomPermRoles['roomInfos'][$nc3RoomId]['content_creatable_value'];
				CalendarPermissiveRooms::$roomPermRoles['roomInfos'][$nc3RoomId]['content_creatable_value'] = true;

				if (!$this->__saveCalendarEventFromGeneratedData($nc2CalendarPlan, $data)) {
					$CalendarActionPlan->rollback();
					CalendarPermissiveRooms::$roomPermRoles['roomInfos'][$nc3RoomId]['content_creatable_value'] = $creatableValue;
					continue;
				}

				CalendarPermissiveRooms::$roomPermRoles['roomInfos'][$nc3RoomId]['content_creatable_value'] = $creatableValue;

				// CalendarActionPlan::saveCalendarPlan から、まわりまわってCalendarEvent::save が呼ばれるので、
				// CalendarEvent::idで取得できる
				$nc2CalendarId = $nc2CalendarPlan['Nc2CalendarPlan']['calendar_id'];
				$idMap = [
					$nc2CalendarId => $CalendarEvent->id
				];
				$this->saveMap('CalendarActionPlan', $idMap);

				$CalendarActionPlan->commit();

			} catch (Exception $ex) {
				// NetCommonsAppModel::rollback()でthrowされるので、以降の処理は実行されない
				// $CalendarActionPlan->saveCalendarPlanでthrowされるとこの処理に入ってこない
				$CalendarActionPlan->rollback($ex);
				throw $ex;
			}
		}

		// 予定登録処理で使用しているデータを空に戻す
		Current::remove('Frame.block_id');
		Current::remove('Frame.room_id');
		Current::remove('Frame.plugin_key');
		Current::remove('Room.id');
		Current::remove('Plugin.key');

		$this->writeMigrationLog(__d('nc2_to_nc3', '  CalendarEvent data Migration end.'));

		return true;
	}

/**
 * Save CalendarEvent from denerated data.
 *
 * @param array $nc2CalendarPlan Nc2CalendarPlan data.
 * @param array $nc3ActionPlan Nc3CalendarActionPlan data.
 * @return bool True on success
 * @throws Exception
 */
	private function __saveCalendarEventFromGeneratedData($nc2CalendarPlan, $nc3ActionPlan) {
		// CalendarActionPlan::saveCalendarPlan呼び出し前の処理
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Controller/CalendarPlansController.php#L382-L401

		/* @var $CalendarActionPlan CalendarActionPlan */
		/* @var $CalendarEvent CalendarEvent */
		$CalendarActionPlan = ClassRegistry::init('Calendars.CalendarActionPlan');
		$CalendarEvent = ClassRegistry::init('Calendars.CalendarEvent');

		// origin_event_idは更新前のCalendarEvent.id
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/View/Elements/CalendarPlans/detail_edit_hiddens.ctp#L16
		$nc3EventId = $nc3ActionPlan['CalendarActionPlan']['origin_event_id'];
		// ない場合メッセージを出力しているので、呼び出す前に存在チェック
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/CalendarEvent.php#L403-L404
		$nc3Event = [];
		$query = [
			'conditions' => [
				'CalendarEvent.id' => $nc3EventId,
			],
			'recursive' => -1
		];
		$nc3EventCount = $CalendarEvent->find('count', $query);
		if ($nc3EventCount) {
			$nc3Event = $CalendarEvent->getEventById($nc3EventId);
		}

		// 更新処理でしか使われてなさげだが、同じような処理にしとく
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/CalendarActionPlan.php#L573-L598
		$saveParameters = $CalendarActionPlan->getProcModeOriginRepeatAndModType($nc3ActionPlan, $nc3Event);
		list($addOrEdit, $isRepeatEvent, $isChangedDteTime, $isChangedRepetition) = $saveParameters;

		// Nc2CalendarPlan.insert_user_idに対応するNc3User.idで良い？
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/Behavior/CalendarInsertPlanBehavior.php#L165-L171
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/Behavior/CalendarUpdatePlanBehavior.php#L381-L383
		/* @var $Nc2ToNc3User Nc2ToNc3User */
		$Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
		$nc3CreatedUser = $Nc2ToNc3User->getCreatedUser($nc2CalendarPlan['Nc2CalendarPlan']);

		// プライベートルームIDは共有イベントでしか使ってなさげ。しかもidとして使用されていない感じ。とりあえずnull
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Controller/CalendarPlansController.php#L589-L596
		// @see https://github.com/NetCommons3/Calendars/blob/3.1.0/Model/Behavior/CalendarPlanGenerationBehavior.php#L276-L283
		$nc3PivateRoomId = null;

		/* 事前バリデーション返却処理をコメントアウト
		$CalendarActionPlan->set($nc3ActionPlan);
		if(!$CalendarActionPlan->validates()){
			$message = __d('nc2_to_nc3', '%s does not migration. validation error.', $this->getLogArgument($nc2CalendarPlan));
			CakeLog::debug(print_r($CalendarActionPlan->validationErrors, true));
			var_export($CalendarActionPlan->validationErrors, true);
			$this->writeMigrationLog($message);
			return false;
		}
		*/
		
		$nc3EventId = $CalendarActionPlan->saveCalendarPlan(
			$nc3ActionPlan,
			$addOrEdit,
			$isRepeatEvent,
			$isChangedDteTime,
			$isChangedRepetition,
			$nc3CreatedUser,
			$nc3PivateRoomId
		);
		if (!$nc3EventId) {
			// print_rはPHPMD.DevelopmentCodeFragmentに引っかかった。
			// var_exportは大丈夫らしい。。。
			// @see https://phpmd.org/rules/design.html
			$message = $this->getLogArgument($nc2CalendarPlan) . "\n" .
			var_export($CalendarActionPlan->validationErrors, true);
			$this->writeMigrationLog($message);

			return false;
		}

		return true;
	}

}

