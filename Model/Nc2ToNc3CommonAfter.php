<?php
/**
 * Nc2ToNc3CommonAfter
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Masayuki Horiguchi <horiguchi@opensource-workshop.jp> https://opensource-workshop.jp/
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3CommonAfter
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
 *
 * @see Nc2ToNc3CommonAfterBehavior
 * @method string getLogArgument($nc2MenuDetail)
 * @method array generateNc3MenuFrameSettingData($nc2MenuDetail)
 * @method array generateNc3MenuFramePageOrRoomData($nc2MenuDetail, $nc3MenuFrameSetting)
 * @method array addOtherRoomAndPageData($nc3MenuFrameSetting)
 *
 */
class Nc2ToNc3CommonAfter extends Nc2ToNc3AppModel {

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3CommonAfter'];

/**
 * Migration method.
 *
 * @return bool True on success.
 */
	public function migrate() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Migration Common After start.'));

		/* ページの並べ替え処理（NC2でのメニューに揃える） */
		if (!$this->__movePageFromNc2Pages()) {
			return false;
		}

		/* 不要なデフォルトブロックを削除する */
		if (!$this->__deleteDefaultFrame()) {
			return false;
		}

		/* ルームを新テーマにする */
		/*
		FullWidthBasicBlue
		FullWidthBasicRed
		*/


		$this->writeMigrationLog(__d('nc2_to_nc3', 'Migration Common After end.'));

		return true;
	}

/**
 * Delete Nc3Frame data.
 *
 * @return bool
 */
	private function __deleteDefaultFrame() {

		/* お知らせモジュールの削除 */
		$Frame = ClassRegistry::init('Frames.Frame');
		$defaultAnnouncementId = 1;
		$data['Frame'] = [
			'id' => 1,//TODO 本当にこれでOKで？
			'plugin_key' => 'announcements',
			'is_deleted' => true,
			'block_id' => 1,//TODO 本当にこれでOKで？
			'weight' => NULL,
			'box_id' => 16,//TODO 本当にこれでOKで？
		];
		if (! $Frame->saveFrame($data)) {
			//エラー処理
			return false;
		}

		return true;
	}

/**
 * Modify Nc3Page data.
 *
 * @return bool
 */
	private function __movePageFromNc2Pages() {
		$Nc2Page = $this->getNc2Model('pages');
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Page Move start.'));
		//1.理想の順序を取得する
		$query = [
			'conditions' => [
				'Nc2Page.parent_id >' => 0,
				'Nc2Page.thread_num >' => 0,
			],
			'order' => [
				'Nc2Page.parent_id ASC',
				'Nc2Page.thread_num ASC',
				'Nc2Page.display_sequence ASC'
			],
		];
		$nc2Pages = $Nc2Page->find('all', $query);

		$nc2PageIds = [];
		foreach ($nc2Pages as $model) {
			$nc2PageIds[] = $model['Nc2Page']['page_id'];
			$nc2ParentIds[] = $model['Nc2Page']['parent_id'];
			$nc2PageDatas[] = [
								'page_id' => $model['Nc2Page']['page_id'],
								'parent_id' => $model['Nc2Page']['parent_id'],
							];
		}
		unset($nc2Pages);

		//2.対象データを理想の順序順に並べ替える
		//対象データを取得
		$Nc2ToNc3Page = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Page');

		$nc2Maps = $Nc2ToNc3Page->getMap($nc2PageIds);
		$nc2ParentMaps = $Nc2ToNc3Page->getMap($nc2ParentIds);
		unset($nc2PageIds);
		unset($nc2ParentIds);

		//登録データを整形
		$nc3MovePageIds = [];
		foreach ($nc2PageDatas as $nc2Page) {
			$nc2Map = $nc2Maps[$nc2Page['page_id']];
			$nc3PageId = $nc2Map['Page']['id'];
			$nc2ParentIdMap =$nc2ParentMaps[$nc2Page['parent_id']];
			$nc3ParentId = $nc2ParentIdMap['Page']['id'];
			//使う？
			$nc3BoxRoomId = $nc2Map['Box']['room_id'];
			$nc3MovePageIds[] = [
				'Page' => [
					'id' => $nc3PageId,
					'room_id' => 1,//とりあえず1 今後階層分対応するかも
					'parent_id' => $nc3ParentId ,//とりあえず1
					'type' => 'bottom',//最下部に移動する
				],
				'Room' => [
					'id' => 1,
				]
			];
		}
		//3.並べ替えた対象データをcount($nc3MovePageIds)分、最下部に移動する
		$Page = ClassRegistry::init('Pages.Page');
		foreach ($nc3MovePageIds as $data) {
			if(!$Page->saveMove($data)){
				return false;
			}
		}
		unset($nc3MovePageIds);
		unset($nc2PageDatas);
	
		$this->writeMigrationLog(__d('nc2_to_nc3', 'Page Move end.'));
		return true;
	}
}