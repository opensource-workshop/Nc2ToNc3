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
	public $actsAs = [
		'Nc2ToNc3.Nc2ToNc3CommonAfter',
		'Nc2ToNc3.Nc2ToNc3Wysiwyg',
	];

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
		/*
		if (!$this->__deleteMenuFrame()) {
			return false;
		}
		*/

		/* デフォルトメニューのフレーム値をnoneにする */
		/*
		if (!$this->__changeDefaultMenuFrame()) {
			return false;
		}
		*/

		/* ヘッダーエリアにあるモジュールのフレーム値をnoneにする */
		/*
		if (!$this->__changeHeaderAllFrame()) {
			return false;
		}
		*/

		/* NC2時にグループで囲われていたモジュールを並び替える */
		if (!$this->__changeGroupBlockSort()) {
			return false;
		}

		/* 緊急連絡のフレーム表示を赤にする */
		if (!$this->__changeEmergencyFrame()) {
			return false;
		}

		/* noneフレームはnoneフレームにする */
		if (!$this->__changeNoneFrame()) {
			return false;
		}

		/* ページのレイアウトを移行する */
		if (!$this->__migratePageLayout()) {
			return false;
		}

		/* サイト管理情報を移行する */
		/* コアにマージするかも */
		if (!$this->__migrateSiteConfig()) {
			return false;
		}

		/* abbreviate_urlを置換する */
		if (!$this->__migrateAbbreviateUrl()) {
			return false;
		}

		/* キャビネットのダウンロード数を移行する */
		if (!$this->__migrateCabinetFileDownloadNum()) {
			return false;
		}

		/* プライベートスペースの利用を不可にする(一般の場合) */
		if (!$this->__changeUserRoleSetting()) {
			return false;
		}

		/* ポータル用でルームごとに各校が管理されていた場合の処理 */
		if (!$this->__adjustMainOnlyLayout()) {
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
 * Migrate Abbreviate Url
 *
 * @return bool
 */
	private function __migrateAbbreviateUrl() {
		$this->writeMigrationLog(__d('nc2_to_nc3', 'AbbreviateUrl Replace start.'));

		$Nc2AbbreviateUrl = $this->getNc2Model('abbreviate_url');
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$BlogEntry = ClassRegistry::init('Blogs.BlogEntry');
		$Announcement = ClassRegistry::init('Announcements.Announcement');
		$connectionObjects = ConnectionManager::enumConnectionObjects();
		$nc3config = $connectionObjects['master'];
		$prefix = $nc3config['prefix'];

		$limit = 100;
		$query = [
			'fields' => 'Nc2AbbreviateUrl.short_url, Nc2AbbreviateUrl.contents_id, Nc2AbbreviateUrl.unique_id',
			'recursive' => -1,
			'joins' => [
				[
					'type' => 'INNER',
					'alias' => 'Nc2JournalPost',
					'table' => 'journal_post',
					'conditions' => 'Nc2AbbreviateUrl.dir_name = "journal" AND Nc2AbbreviateUrl.contents_id = Nc2JournalPost.journal_id AND Nc2AbbreviateUrl.unique_id = Nc2JournalPost.post_id',
				]
			],
			'conditions' => [],
			'order' => ['Nc2AbbreviateUrl.insert_time ASC'], 
			'limit' => $limit,
			'offset' => 0,
		];
		while ($nc2AbbreviateUrls = $Nc2AbbreviateUrl->find('all', $query)) {
			foreach($nc2AbbreviateUrls as $nc2AbbreviateUrl){
				// 移行済みのものだけを対象とする
				$mapIdList = $Nc2ToNc3Map->getMapIdList('BlogEntry', $nc2AbbreviateUrl['Nc2AbbreviateUrl']['unique_id']);
				if (!$mapIdList) {
					continue;
				}
				reset($mapIdList);
				$nc3Id = current($mapIdList);

				// ブログの置換先テキストを作成
				$BlogEntryQuery = [
					'fields' => 'BlogEntry.block_id, BlogEntry.key',
					'conditions' => [
						'BlogEntry.id' => $nc3Id,
					],
				];
				$nc3BlogEntry = $BlogEntry->find('first', $BlogEntryQuery);
				if(!$nc3BlogEntry){
					continue;
				}
				// blogs/blog_entries/view/{blog_entries.block_id}/{blog_entries.key}/
				$replaceText = 'blogs/blog_entries/view/'.$nc3BlogEntry['BlogEntry']['block_id']. '/'. $nc3BlogEntry['BlogEntry']['key']. '/';

				$shortUrl = $nc2AbbreviateUrl['Nc2AbbreviateUrl']['short_url'];
				$tbl = $prefix. "announcements";
				// 更新用（UPDATE）ショートカットURLの後ろにパラメータがついていてもそのまま置換する（たぶん大丈夫なはず）
				$directQuery = "UPDATE $tbl SET content=REPLACE(content, '". $shortUrl. "', '". $replaceText ."');";
				// 確認用（SELECT）
				//$directQuery = "SELECT * FROM $tbl AS Announcement WHERE content LIKE ". "'%". $shortUrl. "%';";
				$targetAnnouncements = $Announcement->query($directQuery);
				if(!count($targetAnnouncements) > 0){
					continue;
				}
			}

			$query['offset'] += $limit;

			//CakeLog::debug(print_r($nc2AbbreviateUrls , true));
			$this->writeMigrationLog(__d('nc2_to_nc3', ' Replace ' . $query['offset'] . 'end.'));
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'AbbreviateUrl Replace end.'));

		return true;
	}

/**
 * Migrate Cabinet File Download Num.
 *
 * @return bool
 */
	private function __migrateCabinetFileDownloadNum() {
		$Nc2CabinetFile = $this->getNc2Model('cabinet_file');
		$query = [
			'fields' => 'Nc2CabinetFile.file_id, Nc2CabinetFile.download_num',
			'conditions' => [],
			'order' => ['Nc2CabinetFile.file_id ASC'], 
		];
		$nc2CabinetFiles = $Nc2CabinetFile->find('all', $query);

		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$CabinetFile = ClassRegistry::init('Cabinets.CabinetFile');
		$UploadFile = ClassRegistry::init('Files.UploadFile');
		foreach($nc2CabinetFiles as $key => $val){
			$mapIdList = $Nc2ToNc3Map->getMapIdList('CabinetFile', $val['Nc2CabinetFile']['file_id']);
			$nc2DownloadNum = $val['Nc2CabinetFile']['download_num'];
			if ($mapIdList) {
				reset($mapIdList);
				$nc3Id = current($mapIdList);
				$query = [
					'fields' => 'CabinetFile.id, CabinetFile.key',
					'conditions' => [
						'CabinetFile.id' => $nc3Id,
						'CabinetFile.is_folder !=' => 1,//フォルダー以外を取得
					],
				];
				$nc3CabinetFile = $CabinetFile->find('first', $query);
				if($nc3CabinetFile){
					$data = [
							'id' => $nc3CabinetFile['UploadFile']['file']['id'],
							'download_count' => $nc2DownloadNum,
							'total_download_count' => $nc2DownloadNum,
					];
					if (!$UploadFile->save($data, array('validate' => false,'callbacks' => false))) {
						return false;
					}
				}
			}
		}
		return true;
	}


/**
 * Migrate Site Config.
 *
 * @return bool
 */
	private function __migrateSiteConfig() {
		/* サイト管理情報を移行する */
		$Nc2Config = $this->getNc2Model('config');
		$query = [
			'fields' => 'Nc2Config.conf_id, Nc2Config.conf_name, Nc2Config.conf_title, Nc2Config.conf_value',
			'conditions' => [
				'OR' => [
					['Nc2Config.conf_name' => 'sitename'],			//サイト名 [nc2_conf_id:1]
					['Nc2Config.conf_name' => 'from'],				//送信者メールアドレス [nc2_conf_id:38]
					['Nc2Config.conf_name' => 'fromname'],			//送信者名 [nc2_conf_id:39]
					['Nc2Config.conf_name' => 'meta_description'],	//サイトの説明 [nc2_conf_id:47]
					['Nc2Config.conf_name' => 'meta_keywords'],		//キーワード [nc2_conf_id:48]
					['Nc2Config.conf_name' => 'meta_robots'],		//ロボット型検索エンジンへの対応 [nc2_conf_id:49]
					['Nc2Config.conf_name' => 'meta_author'],		//作成者 [nc2_conf_id:51]
					['Nc2Config.conf_name' => 'meta_copyright'],	//著作権表示 [nc2_conf_id:52]
				],
			],
			'order' => ['Nc2Config.conf_id ASC'], 
		];
		$nc2Configs = $Nc2Config->find('all', $query);
		$UpdateSiteSettings = [];
		$SiteName  = '';
		foreach($nc2Configs as $key => $val){
			$Nc3SiteSettingKey = '';
			$Nc3SiteSettingId = '';
			$Nc3SiteSettingValue = '';
			switch($val['Nc2Config']['conf_name']){
				case 'sitename':
					$Nc3SiteSettingKey = 'App.site_name';
					$Nc3SiteSettingId = 1;//サイト名（日本語）
					$SiteName = $val['Nc2Config']['conf_value'];
					$Nc3SiteSettingValue = $SiteName;
					break;
				case 'from':
					$Nc3SiteSettingKey = 'Mail.from';
					$Nc3SiteSettingId = 72;
					$Nc3SiteSettingValue = $val['Nc2Config']['conf_value'];
					break;
				case 'fromname':
					$Nc3SiteSettingKey = 'Mail.from_name';
					$Nc3SiteSettingId = 73;//送信者名（日本語）
					$Nc3SiteSettingValue = $val['Nc2Config']['conf_value'];
					break;
				case 'meta_description':
					$Nc3SiteSettingKey = 'Meta.description';
					$Nc3SiteSettingId = 97;//サイトの説明（日本語）
					$Nc3SiteSettingValue = $val['Nc2Config']['conf_value'];
					break;
				case 'meta_keywords':
					$Nc3SiteSettingKey = 'Meta.keywords';
					$Nc3SiteSettingId = 95;//キーワード（日本語）
					$Nc3SiteSettingValue = $val['Nc2Config']['conf_value'];
					break;
				case 'meta_robots':
					//メタロボットインデックスはnoindex,nofollowに
					$Nc3SiteSettingKey = 'Meta.robots';
					$Nc3SiteSettingId = 21;
					$Nc3SiteSettingValue = $val['Nc2Config']['conf_value'];
					//$Nc3SiteSettingValue = 'noindex,nofollow';
					break;
				case 'meta_author':
					$Nc3SiteSettingKey = 'Meta.author';
					$Nc3SiteSettingId = 91;//作成者（日本語）
					//$Nc3SiteSettingValue = $val['Nc2Config']['conf_value'];
					$Nc3SiteSettingValue = $SiteName;
					break;
				case 'meta_copyright':
					$Nc3SiteSettingKey = 'Meta.copyright';
					$Nc3SiteSettingId = 93;//著作権表示（日本語）
					//$Nc3SiteSettingValue = $val['Nc2Config']['conf_value'];
					$Nc3SiteSettingValue = 'Copyright © 2018 '. $SiteName;
					break;
			}
			if ($Nc3SiteSettingKey == '' && $Nc3SiteSettingId == ''){
				continue;
			}
			$UpdateSiteSettings[] = [
				'id' => $Nc3SiteSettingId,
				'key' => $Nc3SiteSettingKey,
				'value' => $Nc3SiteSettingValue,
			];
		}
		//開始ルームをパブリックに
		$UpdateSiteSettings[] = [
				'id' => 4,
				'key' => 'App.default_start_room',
				'value' => 1,
		];
		//メール設定をPHPmail() に
		$UpdateSiteSettings[] = [
				'id' => 76,
				'key' => 'Mail.transport',
				'value' => 'phpmail',
		];

		$SiteSetting = ClassRegistry::init('SiteSettings.SiteSetting');
		if (!$SiteSetting->saveMany($UpdateSiteSettings)) {
			return false;
		}
		return true;
	}

/**
 * Migrate Page Layout.
 *
 * @return bool
 */
	private function __migratePageLayout() {
		/* ページのレイアウトを移行する */
		/* 一旦全てのページのレイアウトを右カラムなしにする */
		$PageContainer = ClassRegistry::init('Pages.PageContainer');
		$query = [
			'fields' => 'PageContainer.id',
			'recursive' => -1,
			'conditions' => [
				'PageContainer.container_type' => 4, //Minor
			],
		];
		$pageContainers = $PageContainer->find('all', $query);
		
		foreach($pageContainers as $val){
			$updated = [
				'PageContainer.is_published' => 0,
			];
			$conditions = [
				'PageContainer.id' => $val['PageContainer']['id']
			];
			$result = $PageContainer->updateAll($updated, $conditions);
		}

		/* Nc2の状態を取得する */
		$Nc2PagesStyle = $this->getNc2Model('pages_style');
		$query = [
			'fields' => 'Nc2PagesStyle.set_page_id,Nc2PagesStyle.header_flag,Nc2PagesStyle.footer_flag,Nc2PagesStyle.leftcolumn_flag,Nc2PagesStyle.rightcolumn_flag',
			'recursive' => -1,
			'joins' => [
				[
					'type' => 'INNER',
					'alias' => 'Nc2Pages',
					'table' => 'pages',
					'conditions' => 'Nc2Pages.page_id = Nc2PagesStyle.set_page_id',
				]
			],
			'conditions' => [
				'Nc2Pages.private_flag' => 0, //プライベートは除く
			],
		];
		$nc2PagesStyle = $Nc2PagesStyle->find('all', $query);

		/* 条件にあったページスタイルをセットする */
		$arrPageIds = [];
		$pageIds = [];
		foreach($nc2PagesStyle as $key => $val){
			$arrPageIds[] = $val['Nc2PagesStyle']['set_page_id'];
			$pageIds[$val['Nc2PagesStyle']['set_page_id']] = [
				'1' => $val['Nc2PagesStyle']['header_flag'],
				'2' => $val['Nc2PagesStyle']['leftcolumn_flag'],
				'4' => $val['Nc2PagesStyle']['rightcolumn_flag'],
				'5' => $val['Nc2PagesStyle']['footer_flag'],
			];
		}

		/* ページスタイルを変更(追記)する */
		if(count($arrPageIds) > 0){
			$Nc2ToNc3Page = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Page');
			$pageMap = $Nc2ToNc3Page->getMap($arrPageIds);
			$nc3UpdatePageContainers = [];
			foreach($pageMap as $nc2Id => $models){
				$pageId = $models['Page']['id'];
				//CakeLog::debug(print_r($models['Page']['permalink'] , true));
				foreach($pageIds[$nc2Id] as $containerType => $isPublished){
					$updated = [
						'PageContainer.is_published' => $isPublished,
					];
					$conditions = [
						'PageContainer.is_configured' => false,
						'PageContainer.page_id' => $pageId,
						'PageContainer.container_type' => $containerType,
					];
					$result = $PageContainer->updateAll($updated, $conditions);
				}
			}
		}
		return true;
	}


/**
 * Adjust Main Only Layout.
 *
 * @return bool
 */
	private function __adjustMainOnlyLayout() {

		$Nc2ToNc3 = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3');
		$nc2BaseUrl = Hash::get($Nc2ToNc3->data, ['Nc2ToNc3', 'base_url']);
		//自治体ポータルでない場合には正常終了
		if(!preg_match('/.*?\.gr.*?\.ed\.jp$/', $nc2BaseUrl, $m)) {
			return true;
		}
		
		//TODO 左か下かの判断
		$majorLayoutFlg = false;
		if(true) {
			$majorLayoutFlg = true;
		}

		//カレントルームを取得する
		$Page = ClassRegistry::init('Pages.Page');
		$query = [
			'recursive' => -1,
			'conditions' => [
				['Page.root_id' => '1'],
				['Page.parent_id' => '1'],
				['Page.room_id !=' => '1'],
			],
			'order' => ['Page.id ASC'], 
		];
		$Nc3CurrentRooms = $Page->find('all', $query);

		$strLayout = '1_0_0_1';		//1_0_0_1 上下
		if($majorLayoutFlg){
			$strLayout = '1_1_0_0';	//1_1_0_0 上左
		}
		foreach($Nc3CurrentRooms as $val){
			//ページ設定（上下or上左）にする
			if(!$this->setPagesLayout($val, $strLayout)) return false;

			//ヘッダーをルーム共通にする
			//contentType=1//ヘッダー
			$RoomBoxId = $this->setBoxesDisplay($val, 1);
			if(!$RoomBoxId) return false;

			//ヘッダーにメニューフレームを配置する
			$FrameKey = $this->addFramesToBoxId($val, $RoomBoxId, 'menus');
			if(!$FrameKey) return false;

			//ヘッダーのメニューフレームのテンプレートをheader_flatにする
			if(!$this->changeMenuFrameSettingDisplayType($FrameKey, 'header_flat')) return false;

			//ヘッダーにお知らせフレームを配置する
			$FrameKey = $this->addFramesToBoxId($val, $RoomBoxId, 'announcements');
			if(!$FrameKey) return false;

			//メインのメニュー削除
			if(!$this->deleteMainMenuFrame($val)) return false;

			//フッター or 左カラムをルーム共通にする
			//contentType=2 or 5//ヘッダー
			$contentType = 2;
			if (!$majorLayoutFlg){
				$contentType = 5;
			}
			$RoomBoxId = $this->setBoxesDisplay($val, $contentType);
			if(!$RoomBoxId) return false;

			//後から追加したものが上にくるので最初にカウンター
			//フッター or 左カラムにアクセスカウンターフレームを配置する(あったやつだけ)
			$FrameKey = $this->moveAccesscounter($val, $RoomBoxId);

//TODO
			//QRコード
			$val['Frame']['header_type'] = 'default';
			$val['FramesLanguage']['name'] = 'QRコード';
			$FrameKey = $this->addFramesToBoxId($val, $RoomBoxId, 'announcements');
			if(!$FrameKey) return false;
			unset($val['Frame']['header_type']);
			unset($val['FramesLanguage']['name']);
			//お知らせにコンテンツをセット
			if(!$this->setAnnouncementBlock($val, $FrameKey)) return false;

			//メインエリアの「学校の連絡先」お知らせフレームを削除し、選択されていたブロックをフッター or 左カラムのお知らせに登録する
			$FramesLanguageName = '学校の連絡先';
			$MainFramesBlockId = $this->deleteFramesAndReturnVal($val, $FramesLanguageName);
			if($MainFramesBlockId) {
				//フッター or 左カラムにお知らせフレームを配置する
				$val['Frame']['block_id'] = $MainFramesBlockId;
				$val['Frame']['header_type'] = 'default';
				$val['FramesLanguage']['name'] = $FramesLanguageName;
				$FrameKey = $this->addFramesToBoxId($val, $RoomBoxId, 'announcements');
				if(!$FrameKey) return false;
				unset($val['Frame']['block_id']);
				unset($val['Frame']['header_type']);
				unset($val['FramesLanguage']['name']);
			}

			//左カラムありの場合は左にメニューを追加する
			if ($majorLayoutFlg){
				$FrameKey = $this->addFramesToBoxId($val, $RoomBoxId, 'menus');
				//左メニューフレームのテンプレートをminor_and_firstにする
				if(!$this->changeMenuFrameSettingDisplayType($FrameKey, 'minor_and_first')) return false;
			}

		}

		//CakeLog::debug(print_r($Nc3CurrentRooms, true));

		return true;
	}

/**
 * Change User Role Setting.
 *
 * @return bool
 */
	private function __changeUserRoleSetting() {
		$UserRoleSetting = ClassRegistry::init('UserRoles.UserRoleSetting');
		/* プライベートスペースの利用を不可にする(一般の場合) */
		$data['UserRoleSetting'] = [
			'id' => 3, //3
			'role_key' => 'common_user',
			'origin_role_key' => 'common_user',
			'use_private_room' => 0,
		];
		if (! $UserRoleSetting->saveUserRoleSetting($data)) {
			//エラー処理
			return false;
		}

		/* プライベートスペースの利用を不可にする(サイト管理者の場合) */
		$data['UserRoleSetting'] = [
			'id' => 2, //2
			'role_key' => 'administrator',
			'origin_role_key' => 'administrator',
			'use_private_room' => 0,
		];
		if (! $UserRoleSetting->saveUserRoleSetting($data)) {
			//エラー処理
			return false;
		}

		/* プライベートスペースの利用を不可にする(システム管理者の場合) */
		$data['UserRoleSetting'] = [
			'id' => 1, //1
			'role_key' => 'system_administrator',
			'origin_role_key' => 'system_administrator',
			'use_private_room' => 0,
		];
		if (! $UserRoleSetting->saveUserRoleSetting($data)) {
			//エラー処理
			return false;
		}

		return true;
	}

/**
 * Change None Frame.
 *
 * @return bool
 */
	private function __changeNoneFrame() {

		//お知らせモジュール noneframeの場合にnoneframeに変更する
		$Nc2Announcement = $this->getNc2Model('announcement');
		$query = [
			'fields' => 'Nc2Announcement.block_id',
			'recursive' => -1,
			'joins' => [
				[
					'type' => 'INNER',
					'alias' => 'Nc2Blocks',
					'table' => 'blocks',
					'conditions' => 'Nc2Blocks.block_id = Nc2Announcement.block_id',
				],
			],
			'conditions' => [
				'Nc2Blocks.theme_name' => 'noneframe',
			],
		];
		$nc2Announcements = $Nc2Announcement->find('all', $query);
		$UpdateFrames = [];
		if(count($nc2Announcements) > 0){
			$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
			foreach ($nc2Announcements as $key => $val){
				$nc3AnnouncementFrame = $Nc2ToNc3Frame->getMap($val['Nc2Announcement']['block_id']);
				if (!$nc3AnnouncementFrame) {
					continue;
				}
				$UpdateFrames[]['Frame'] = [
					'id' => $nc3AnnouncementFrame['Frame']['id'],
					'plugin_key' => 'announcements',
				];
			}
		}

		$Frame = ClassRegistry::init('Frames.Frame');
		/* 空のフレーム表示をnoneにする */
/*
		$query = [
			'fields' => 'Frame.id, Frame.plugin_key',
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
				'FramesLanguages.name' => '',
			],
		];
		$frames = $Frame->find('all', $query);
		$UpdateFrames = array_merge($UpdateFrames, $frames);
*/

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
 * Change Emergency Frame.
 *
 * @return bool
 */
	private function __changeEmergencyFrame() {

		//お知らせモジュール titleaccent(赤),titleline_redの場合にもFrameの色を変更する
		$Nc2Announcement = $this->getNc2Model('announcement');
		$query = [
			'fields' => 'Nc2Announcement.block_id',
			'recursive' => -1,
			'joins' => [
				[
					'type' => 'INNER',
					'alias' => 'Nc2Blocks',
					'table' => 'blocks',
					'conditions' => 'Nc2Blocks.block_id = Nc2Announcement.block_id',
				],
			],
			'conditions' => [
				'OR' => [
					['Nc2Blocks.theme_name' => 'titleaccent'],
					['Nc2Blocks.theme_name' => 'titleline_red'],
				],
			],
		];
		$nc2Announcements = $Nc2Announcement->find('all', $query);
		$UpdateFrames = [];
		if(count($nc2Announcements) > 0){
			$Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
			foreach ($nc2Announcements as $key => $val){
				$nc3AnnouncementFrame = $Nc2ToNc3Frame->getMap($val['Nc2Announcement']['block_id']);
				if (!$nc3AnnouncementFrame) {
					continue;
				}
				$UpdateFrames[]['Frame'] = [
					'id' => $nc3AnnouncementFrame['Frame']['id'],
					'plugin_key' => 'announcements',
				];
			}
		}

		$Frame = ClassRegistry::init('Frames.Frame');
		/* 緊急連絡のフレーム表示を赤にする */
		$query = [
			'fields' => 'Frame.id, Frame.plugin_key',
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
				'FramesLanguages.name' => '緊急連絡',
			],
		];
		$frames = $Frame->find('all', $query);
		$UpdateFrames = array_merge($UpdateFrames, $frames);

		foreach ($UpdateFrames as $UpdateFrame) {
			/* 抽出された全てのフレームをdangerにする */
			$data['Frame'] = [
				'id' => $UpdateFrame['Frame']['id'],
				'plugin_key' => $UpdateFrame['Frame']['plugin_key'],
				'header_type' => 'danger',
			];
			if (! $Frame->saveFrame($data)) {
				//エラー処理
				return false;
			}
		}
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
				//'OR' => array(
				//	array('Boxes.container_type' => 2), 
				//	array('Boxes.container_type' => 4),
				//),
				//左にあるメニューだけ削除に修正
				'Boxes.container_type' => 2, //1:Header, 2:Major, 3:Main, 4:Minor, 5:Footer
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


		/* デフォルトメニューフレームID*/
		$defaultMenuFrameId = 2;

		$data['Frame'] = [
			'id' => $defaultMenuFrameId,
			'plugin_key' => 'menus',
			'is_deleted' => true,
			'block_id' => 1,
			'weight' => NULL,//TODO 一番下だからNULLで良い？
			'box_id' => 18,
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
				if(!isset($nc2Maps[$nc2PageId])){
					continue;
				}
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

		/* トップページがルームの場合の処理 */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$query = [
			'fields' => [
				'nc2_id',
				'nc3_id',
			],
			'conditions' => [
				'nc3_id' => '4',//TOPページ初期値
				'model_name' => 'Page',
			],
			'recursive' => -1
		];
		$idList = $Nc2ToNc3Map->find('list', $query);
		$result = $Page->existPage( 4, 1, 1);//存在チェック
		if (!$idList && $result) {
			$nc3PageTop = [
				'Page' => [
					'id' => 4,
					'room_id' => 1,
					'parent_id' => 1,
					'type' => 'bottom',
				],
				'Room' => [
					'id' => 1,
				]
			];
			if(!$Page->saveMove($nc3PageTop)){
				return false;
			}
			// ページ削除
			$data['Page']['id'] = 4;
			if(!$Page->deletePage($data)){
				return false;
			}
		}
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