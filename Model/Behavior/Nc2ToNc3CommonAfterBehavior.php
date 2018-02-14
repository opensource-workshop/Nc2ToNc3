<?php
/**
 * Nc2ToNc3CommonAfterBehavior
 *
 * @copyright Copyright 2017, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3CommonAfterBehavior
 *
 */
class Nc2ToNc3CommonAfterBehavior extends Nc2ToNc3BaseBehavior {


	public function setPagesLayout(Model $model, $Data, $strLayout) {
		switch($strLayout){
			case '1_0_0_1':// 上下
				$contentType = [1, 3, 5];
			break;
			case '1_1_0_0':// 上左
				$contentType = [1, 2, 3];
			break;
			default:
				$contentType = [1, 2, 3];
			break;
		}

		$RoomId = $Data['Page']['room_id'];
		$Page = ClassRegistry::init('Pages.Page');
		$query = [
			'fields' => 'Page.id, Page.room_id, Page.root_id, Page.parent_id, PageContainer.id, PageContainer.container_type',
			'recursive' => -1,
			'conditions' => [
				['Page.room_id' => $RoomId],
			],
			'joins' => [
				[
					'type' => 'INNER',
					'alias' => 'PageContainer',
					'table' => 'page_containers',
					'conditions' => 'PageContainer.page_id = Page.id',
				],
			],
			'order' => ['Page.id ASC'], 
		];
		$UpdatePages = $Page->find('all', $query);
		$PageContainer = ClassRegistry::init('Pages.PageContainer');
		foreach($UpdatePages as $val){
			$published = 0;
			//セットされたエリアのみ表示する
			if(in_array($val['PageContainer']['container_type'], $contentType)){
				$published = 1;
			}
			$updated = [
				'PageContainer.is_published' => $published,
			];
			$conditions = [
				'PageContainer.id' => $val['PageContainer']['id']
			];
			if(!$PageContainer->updateAll($updated, $conditions)){
				return false;
			}
		}
		return true;
	}

	public function setBoxesDisplay(Model $model, $Data, $containerType) {
		//サイト共通表示をオフ
		$Box = ClassRegistry::init('Boxes.Box');
		$query = [
			'fields' => 'Box.id',
			'recursive' => -1,
			'conditions' => [
				['Box.container_type' => $containerType],
				['Box.type' => 1],//サイト共通のBoxesを取得
			],
			'order' => ['Box.id ASC'], 
		];
		$SiteCommonBoxId = $Box->find('first', $query);

		$RoomId = $Data['Page']['room_id'];
		$SiteCommonBoxId = $SiteCommonBoxId['Box']['id'];
		$BoxesPageContainer = ClassRegistry::init('Boxes.BoxesPageContainer');
		$query = [
			'fields' => ['BoxesPageContainer.id'],
			'recursive' => -1,
			'conditions' => [
				['BoxesPageContainer.container_type' => $containerType],
				['BoxesPageContainer.box_id' => $SiteCommonBoxId],
				['Page.room_id' => $RoomId],
			],
			'joins' => [
				[
					'type' => 'INNER',
					'alias' => 'Page',
					'table' => 'pages',
					'conditions' => 'BoxesPageContainer.page_id = Page.id',
				],
			],
			'order' => ['BoxesPageContainer.id ASC'], 
		];
		$UpdateBoxesPageContaines = $BoxesPageContainer->find('all', $query);
//CakeLog::debug(print_r($UpdateBoxesPageContaines, true));
		$tmpData['BoxesPageContainer']['is_published'] = 0;
		foreach($UpdateBoxesPageContaines as $val){
			if(!$BoxesPageContainer->save(Hash::merge($tmpData, $val))) return false;
		}

		//ルーム共通をON
		$query = [
			'fields' => 'Box.id, Box.room_id, Box.page_id, Box.type, '
			.'BoxesPageContainer.id, BoxesPageContainer.box_id, BoxesPageContainer.is_published',
			'recursive' => -1,
			'conditions' => [
				['Box.container_type' => $containerType],
				['Box.type' => 3],//ルーム共通のBoxesを取得
				['Box.room_id' => $RoomId],
			],
			'joins' => [
				[
					'type' => 'INNER',
					'alias' => 'BoxesPageContainer',
					'table' => 'boxes_page_containers',
					'conditions' => 'BoxesPageContainer.box_id = Box.id',
				],
			],
			'order' => ['Box.id ASC'], 
		];
		$UpdateBoxesPageContaines = $Box->find('all', $query);
		foreach($UpdateBoxesPageContaines as $val){
			$val['BoxesPageContainer']['is_published'] = 1;
			if(!$BoxesPageContainer->save($val))return false;
		}

		return Hash::get($UpdateBoxesPageContaines, '0.Box.id');
	}

	public function addFramesToBoxId(Model $model, $Data, $BoxId, $PluginKey) {
		$Frame = ClassRegistry::init('Frames.Frame');
		$Frame->create();
		$RoomId = $Data['Page']['room_id'];
		$updateData['Frame']['room_id'] = $RoomId;
		$updateData['Frame']['box_id'] = $BoxId;
		$updateData['Frame']['plugin_key'] = $PluginKey;
		$updateData['Frame']['header_type'] = 'none';
		$updateData['Frame']['is_deleted'] = false;
		$updateData['FramePublicLanguage']['language_id'] = array('0');
		$updateData['Frame']['header_type'] = 'none';
		if(Hash::get($Data, 'Frame.header_type')){
			$updateData['Frame']['header_type'] = Hash::get($Data, 'Frame.header_type');
		}
		if(Hash::get($Data, 'Frame.block_id')){
			$updateData['Frame']['block_id'] = Hash::get($Data, 'Frame.block_id');
		}
		$updateData['FramesLanguage']['name'] = false;
		if(Hash::get($Data, 'FramesLanguage.name')){
			$updateData['FramesLanguage']['name'] = Hash::get($Data, 'FramesLanguage.name');
		}

		$query = [
					'conditions' => [
						'Frame.room_id' =>  $RoomId,
						'Frame.box_id' =>  $BoxId,
						'Frame.plugin_key' =>  $PluginKey,
					],
		];
		//最初だけセットする
		$findData = $Frame->find('first', $query);
		if($findData) {
			return Hash::get($findData, 'Frame.key');
		}
		$retData = $Frame->saveFrame($updateData);
		if(!$retData){
			return false;
		}

		return Hash::get($retData, 'Frame.key');
	}
	
	public function changeMenuFrameSettingDisplayType(Model $model, $FrameKey, $DisplayType) {
		$MenuFrameSetting = ClassRegistry::init('Menus.MenuFrameSetting');
		$query = [
			'fields' => 'MenuFrameSetting.id',
			'conditions' => [
				'MenuFrameSetting.frame_key' => $FrameKey,
				'MenuFrameSetting.display_type' => $DisplayType,
			]
		];
		$findData = $MenuFrameSetting->find('first', $query);
		if($findData) {
			return true;
		}
		$MenuId = Hash::get($findData, 'MenuFrameSetting.id');

		$data['id'] = $MenuId;
		$data['frame_key'] = $FrameKey;
		$data['display_type'] = $DisplayType;
		if (!$MenuFrameSetting->save($data, array('validate' => false,'callbacks' => false))) {
			return false;
		}

		return true;
	}

	public function moveAccesscounter(Model $model, $Data, $BoxId ) {
		$PluginKey = 'access_counters';
		$RoomId = $Data['Page']['room_id'];
		$Block = ClassRegistry::init('Blocks.Block');

		$query = [
			'fields' => [
				'Block.id',
				'MAX(AccessCounter.count) AS "max_count"'
			],
			'conditions' => [
				'Block.room_id' => $RoomId,
				'Block.plugin_key' => $PluginKey,
			],
			'joins' => [
				[
					'type' => 'INNER',
					'alias' => 'AccessCounter',
					'table' => 'access_counters',
					'conditions' => 'Block.key = AccessCounter.block_key',
				],
			],
		];
		$findData = $Block->find('first', $query);
		$MaxCount = Hash::get($findData, '0.max_count');
		if(!$MaxCount > 0){
			return true;
		}
		//MaxCountがあった場合はBlockIDをFrameに追記
		$BlockId = Hash::get($findData, 'Block.id');

		//アクセスカウンター追加
		$Data['Frame']['block_id'] = $BlockId;
		$Data['Frame']['header_type'] = 'default';
		$Data['FramesLanguage']['name'] = 'アクセスカウンター';
		$FrameKey = $this->addFramesToBoxId($Block, $Data, $BoxId, $PluginKey);
		if(!$FrameKey) return false;

	}

	public function deleteMainMenuFrame(Model $model, $Data) {
		/* メインにあるメニューを削除する */
		$RoomId = $Data['Page']['room_id'];
		$Frame = ClassRegistry::init('Frames.Frame');
		$query = [
			'fields' => 'Frame.id, Frame.plugin_key, Frame.box_id, Frame.weight',
			'recursive' => -1,
			'joins' => [
				[
					'type' => 'INNER',
					'alias' => 'Box',
					'table' => 'boxes',
					'conditions' => 'Box.id = Frame.box_id',
				]
			],
			'conditions' => [
				'Box.container_type' => 3, //1:Header, 2:Major, 3:Main, 4:Minor, 5:Footer
				'Box.room_id' => $RoomId,
				'Frame.plugin_key' => 'menus',
			],
		];
		$UpdateFrames = $Frame->find('all', $query);
		foreach ($UpdateFrames as $UpdateFrame) {
			$data['Frame'] = [
				'id' => $UpdateFrame['Frame']['id'],
				'plugin_key' => $UpdateFrame['Frame']['plugin_key'],
				'weight' => $UpdateFrame['Frame']['weight'],
				'box_id' => $UpdateFrame['Frame']['box_id'],
				'is_deleted' => true,
			];
			if (! $Frame->saveFrame($data)) return false;
		}

		return true;
	}

	public function deleteFramesAndReturnVal(Model $model, $Data, $FrameName) {
		/* 学校の連絡先のフレームのblock_keyを取得 */
		$RoomId = $Data['Page']['room_id'];
		$Frame = ClassRegistry::init('Frames.Frame');
		$query = [
			'fields' => 'Frame.id, Frame.plugin_key, Frame.box_id, Frame.weight, Frame.block_id',
			'recursive' => -1,
			'joins' => [
				[
					'type' => 'INNER',
					'alias' => 'FramesLanguages',
					'table' => 'frames_languages',
					'conditions' => 'FramesLanguages.frame_id = Frame.id',
				]
			],
			'conditions' => [
				'Frame.room_id' => $RoomId,
				'FramesLanguages.name' => $FrameName,
			],
		];
		$UpdateFrames = $Frame->find('all', $query);
		foreach ($UpdateFrames as $UpdateFrame) {
			$data['Frame'] = [
				'id' => $UpdateFrame['Frame']['id'],
				'plugin_key' => $UpdateFrame['Frame']['plugin_key'],
				'weight' => $UpdateFrame['Frame']['weight'],
				'box_id' => $UpdateFrame['Frame']['box_id'],
				'is_deleted' => 0,//削除
			];
			if (! $Frame->saveFrame($data)) return false;
		}
		return Hash::get($UpdateFrames, '0.Frame.block_id');
	}
}
