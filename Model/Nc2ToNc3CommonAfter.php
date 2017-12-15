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

		/* 右、左にあるメニューを削除する */
		if (!$this->__deleteMenuFrame()) {
			return false;
		}

		/* デフォルトメニューのフレーム値をnoneにする */
		if (!$this->__changeDefaultMenuFrame()) {
			return false;
		}

		/* ヘッダーエリアにあるモジュールのフレーム値をnoneにする */
		if (!$this->__changeHeaderAllFrame()) {
			return false;
		}

		/* NC2時にグループで囲われていたモジュールを並び替える */
		if (!$this->__changeGroupBlockSort()) {
			return false;
		}

		/* 緊急連絡のフレーム表示を赤にする */
		if (!$this->__changeEmergencyFrame()) {
			return false;
		}

		/* ページのレイアウトを移行する */
		/* コアにマージするかも */
		if (!$this->__migratePageLayout()) {
			return false;
		}else{
			/* 右、左、ヘッダー、なしレイアウトの場合にレイアウト含めたデザインを調整する */
			if (!$this->__adjustMainOnlyLayout()) {
				return false;
			}
		}

		/* サイト管理情報を移行する */
		/* コアにマージするかも */
		if (!$this->__migrateSiteConfig()) {
			return false;
		}


		/* ルームを新テーマにする 今は不要 */
		/*
		if (!$this->__changeDefaultTheme()) {
			return false;
		}
		*/

		/*
			各NCでの個別処理は下記に記載する
		*/
		/* ヘッダーフォトアルバム削除する */
		/* ヘッダーブロックの結合 2つのブロックをdiv float:leftで囲み、不要なフレームは削除 */


		$this->writeMigrationLog(__d('nc2_to_nc3', 'Migration Common After end.'));

		return true;
	}




/**
 * Migrate Site Config.
 *
 * @return bool
 */
	private function __migrateSiteConfig() {
		/* サイト管理情報を移行する */
		/* コアにマージするかも */
		return true;
	}

/**
 * Migrate Page Layout.
 *
 * @return bool
 */
	private function __migratePageLayout() {
		/* ページのレイアウトを移行する */
		return true;
	}


/**
 * Adjust Main Only Layout.
 *
 * @return bool
 */
	private function __adjustMainOnlyLayout() {
		/*__migratePageLayoutを実行後に実行 */
		/* 右、左、ヘッダー、なしレイアウトの場合にレイアウト含めたデザインを調整する */
		return true;
	}

/**
 * Change Emergency Frame.
 *
 * @return bool
 */
	private function __changeEmergencyFrame() {
		/* 緊急連絡のフレーム表示を赤にする */
		return true;
	}

/**
 * Change Group Block Sort.
 *
 * @return bool
 */
	private function __changeGroupBlockSort() {
		/* NC2時にグループで囲われていたモジュールを並び替える */
		/* 結構難しそう */
		return true;
	}


/**
 * Change Header All Frame.
 *
 * @return bool
 */
	private function __changeHeaderAllFrame() {
		$Frame = ClassRegistry::init('Frames.Frame');
		$query = [
			'fields' => 'Frame.id, Frame.plugin_key',
			'recursive' => -1,
			'joins' => [
				[
					'type' => 'INNER',
					'alias' => 'Boxes',
					'table' => 'boxes',
					'conditions' => 'Boxes.id = Frame.box_id',
				]
			],
			'conditions' => [
				'Boxes.container_type' => 1, //1:Header, 2:Major, 3:Main, 4:Minor, 5:Footer
			],
		];
		$UpdateFrames = $Frame->find('all', $query);
		foreach ($UpdateFrames as $UpdateFrame) {
			/* 抽出された全てのフレームをnoneにする */
			$data['Frame'] = [
				'id' => $UpdateFrame['Frame']['id'],
				'plugin_key' => $UpdateFrame['Frame']['plugin_key'],
				'header_type' => 'none',
			];
			if (! $Frame->saveFrame($data)) {
				//エラー処理
				return false;
			}
		}
		return true;
	}

/**
 * Change Default Menu Frame.
 *
 * @return bool
 */
	private function __changeDefaultMenuFrame() {
		/* デフォルトメニューのフレーム値をnoneにしてタイトルを空白にする */
		$Frame = ClassRegistry::init('Frames.Frame');
		$data['Frame'] = [
			'id' => 2,
			'plugin_key' => 'menus',
			'header_type' => 'none',
		];
		$data['FramesLanguage'] = [
			'id' => 2,
			'frame_id' => 2,
			'name' => '',
		];
		if (! $Frame->saveFrame($data)) {
			//エラー処理
			return false;
		}

		return true;
	}


/**
 * Delete Menu data.
 *
 * @return bool
 */
	private function __deleteMenuFrame() {
		/* デフォルトメニューフレームID*/
		$defaultMenuFrameId = 2;
		
		/* 右、左にあるメニューを削除する */
		$Frame = ClassRegistry::init('Frames.Frame');
		$query = [
			'fields' => 'Frame.id, Frame.plugin_key, Frame.box_id, Frame.weight',
			'recursive' => -1,
			'joins' => [
				[
					'type' => 'INNER',
					'alias' => 'Boxes',
					'table' => 'boxes',
					'conditions' => 'Boxes.id = Frame.box_id',
				]
			],
			'conditions' => [
				'OR' => array(
					array('Boxes.container_type' => 2), //1:Header, 2:Major, 3:Main, 4:Minor, 5:Footer
					array('Boxes.container_type' => 4),
				),
				'Frame.id !=' => $defaultMenuFrameId, //デフォルトメニューフレームは削除しない
				'Frame.plugin_key' => 'menus', //今の所メニューだけ削除
			],
		];
		$UpdateFrames = $Frame->find('all', $query);
		foreach ($UpdateFrames as $UpdateFrame) {
			$data['Frame'] = [
				'id' => $UpdateFrame['Frame']['id'],
				'plugin_key' => $UpdateFrame['Frame']['plugin_key'],
				'is_deleted' => true,
				'weight' => $UpdateFrame['Frame']['weight'],
				'box_id' => $UpdateFrame['Frame']['box_id'],
			];
			if (! $Frame->saveFrame($data)) {
				//エラー処理
				return false;
			}
		}
		unset($UpdateFrames);

		/*デフォルトメニューを一番上に表示させる*/
		/* 左カラムの表示中のフレームを取得 */
		$boxId = 18;//左カラムのbox_idは18
		$query = [
			'fields' => 'Frame.id, Frame.plugin_key, Frame.box_id, Frame.weight',
			'conditions' => [
				'Frame.box_id' => $boxId,
				'Frame.weight !=' => NULL,
				'Frame.is_deleted' => 0,
				'Frame.id !=' => $defaultMenuFrameId,
			],
			'order' => ['Frame.weight DESC'], 
		];
		$SortFrames = $Frame->find('all', $query);
		/* 取得したフレームを一つずつ下げていく */
		foreach ($SortFrames as $SortFrame) {
			$data['Frame'] = [
				'id' => $SortFrame['Frame']['id'],
				'plugin_key' => $SortFrame['Frame']['plugin_key'],
				'weight' => $SortFrame['Frame']['weight'],
				'box_id' => $SortFrame['Frame']['box_id'],
			];
			if (! $Frame->saveWeight($data, 'down')) {
				//エラー処理
				return false;
			}
		}

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
		$data['Frame'] = [
			'id' => 1,
			'plugin_key' => 'announcements',
			'is_deleted' => true,
			'block_id' => 1,
			'weight' => NULL,//TODO 一番下だからNULLで良い？
			'box_id' => 16,//デフォルトメインエリアのbox_idは18
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
				'Nc2Page.parent_id !=' => 2, //グループスペースは除く
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
			$nc2PageDatas[$model['Nc2Page']['parent_id']][] = $model['Nc2Page']['page_id'];
		}
		unset($nc2Pages);

		//2.対象データを理想の順序順に並べ替える
		//対象データを取得
		$Nc2ToNc3Page = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Page');

		$nc2Maps = $Nc2ToNc3Page->getMap($nc2PageIds);
		//グループスペースは移行mapに入っていない
		$nc2ParentMaps = $Nc2ToNc3Page->getMap(array_unique($nc2ParentIds));
		unset($nc2PageIds);
		unset($nc2ParentIds);

		//登録データを整形
		asort($nc2PageDatas);
		$Page = ClassRegistry::init('Pages.Page');
		$nc3MovePageIds = [];
		foreach ($nc2PageDatas as $nc2ParentId => $nc2PageIds) {

			// 移行対象じゃないルームは除く
			if (!array_key_exists($nc2ParentId, $nc2ParentMaps)){
				continue;
			}

			// NC3のルーム内のページが一つの場合は除く
			if(count($nc2PageIds) === 1) {
				continue;
			}

			/* NC3でのルーム最下層PageIdを取得 */
			$nc2ParentIdMap = $nc2ParentMaps[$nc2ParentId];
			$nc3ParentId = $nc2ParentIdMap['Page']['id'];
			$query = [
				'fields' => 'Page.id, Page.parent_id, Page.lft, Page.rght',
				'recursive' => -1,
				'conditions' => [
					'Page.parent_id' => $nc3ParentId,
				],
				'order' => ['Page.rght DESC'], 
			];
			$Nc3BottomPageData = $Page->find('first', $query);
			$Nc3BottomPageId = $Nc3BottomPageData['Page']['id'];

			foreach ($nc2PageIds as $nc2PageId) {
				$nc2Map = $nc2Maps[$nc2PageId];
				$nc3PageId = $nc2Map['Page']['id'];
				//最初の並べ替えページが最下部のページだった場合に並べ替えはしない
				if($nc2PageId === reset($nc2PageIds) && $Nc3BottomPageId === $nc3PageId){
					continue;
				}

				$nc3BoxRoomId = $nc2Map['Box']['room_id'];
				$nc3MovePageIds[] = [
					'Page' => [
						'id' => $nc3PageId,
						'room_id' => $nc3BoxRoomId,
						'parent_id' => $nc3ParentId ,
						'type' => 'bottom',//最下部に移動する
					],
					'Room' => [
						'id' => $nc3BoxRoomId,
					]
				];
			}
		}
		//3.並べ替えた対象データをcount($nc3MovePageIds)分、最下部に移動する
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

/**
 * Change Default Theme.
 *
 * @return bool
 */
	private function __changeDefaultTheme() {
		$Room = ClassRegistry::init('Rooms.Room');

		$data['Room'] = [
			'id' => 1,
			'theme' => "FullWidthBasicRed",
		];
		if (! $Room->saveTheme($data)) {
			//エラー処理
			return false;
		}
		return true;
	}

}