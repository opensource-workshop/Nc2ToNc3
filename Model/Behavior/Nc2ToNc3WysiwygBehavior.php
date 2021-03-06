<?php
/**
 * Nc2ToNc3WysiwygBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');
App::uses('WysiwygBehavior', 'Wysiwyg.Model/Behavior');
App::uses('File', 'Utility');

/**
 * Nc2ToNc3WysiwygBehavior
 *
 */
class Nc2ToNc3WysiwygBehavior extends Nc2ToNc3BaseBehavior {

/**
 * Convert nc2 content.
 *
 * @param Model $model Model using this behavior
 * @param string $content Nc2 content.
 * @return string converted nc3 body.
 */
	public function convertWYSIWYG(Model $model, $content) {
		$searches = [];
		$replaces = [];

		/* 順序入れ替え __getStrReplaceArgumentsOfBaseUrlLink の後に実行
		$strReplaceArguments = $this->__getStrReplaceArgumentsOfDownloadAction($content);
		if ($strReplaceArguments) {
			$searches = array_merge($searches, $strReplaceArguments[0]);
			$replaces = array_merge($replaces, $strReplaceArguments[1]);
		}
		*/

		$strReplaceArguments = $this->__getStrReplaceArgumentsOfTitleIcon($content);
		if ($strReplaceArguments) {
			$searches = array_merge($searches, $strReplaceArguments[0]);
			$replaces = array_merge($replaces, $strReplaceArguments[1]);
		}

		$strReplaceArguments = $this->__getStrReplaceArgumentsOfBaseUrlLink($content);
		if ($strReplaceArguments) {
			$searches = array_merge($searches, $strReplaceArguments[0]);
			$replaces = array_merge($replaces, $strReplaceArguments[1]);
		}

		$strReplaceArguments = $this->__getStrReplaceArgumentsOfDownloadAction($content);
		if ($strReplaceArguments) {
			$searches = array_merge($searches, $strReplaceArguments[0]);
			$replaces = array_merge($replaces, $strReplaceArguments[1]);
		}

		$strReplaceArguments = $this->__getStrReplaceArgumentsOfCabinetFile($content);
		if ($strReplaceArguments) {
			$searches = array_merge($searches, $strReplaceArguments[0]);
			$replaces = array_merge($replaces, $strReplaceArguments[1]);
		}

		$strReplaceArguments = $this->__getStrReplaceArgumentsOfTex($content);
		if ($strReplaceArguments) {
			$searches = array_merge($searches, $strReplaceArguments[0]);
			$replaces = array_merge($replaces, $strReplaceArguments[1]);
		}

		$strReplaceArguments = $this->__getStrReplaceArgumentsOfTable($content);
		if ($strReplaceArguments) {
			$searches = array_merge($searches, $strReplaceArguments[0]);
			$replaces = array_merge($replaces, $strReplaceArguments[1]);
		}

		$content = str_replace($searches, $replaces, $content);
		return $content;
	}

/**
 * Get str_replace arguments of download action.
 *
 * @param string $content Nc2 content.
 * @return array str_replace arguments.(0:$search,1:$replace)
 */
	private function __getStrReplaceArgumentsOfDownloadAction($content) {
		$strReplaceArguments = [];

		/* @var $Nc2ToNc3 Nc2ToNc3 */
		$Nc2ToNc3 = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3');
		$nc2BaseUrl = Hash::get($Nc2ToNc3->data, ['Nc2ToNc3', 'base_url']);
		// http, https混在コンテンツに対応 by mutaguchi@opensource-workshop.jp
		$nc2BaseUrlParse = parse_url($nc2BaseUrl);
		$nc2BaseUrlHttp = 'http://' . $nc2BaseUrlParse['host'];
		$nc2BaseUrlHttps = 'https://' . $nc2BaseUrlParse['host'];
		//$sub_dir = "";
		//if (preg_match("/.*?\:\/\/.*?\/(.*$)/", $nc2BaseUrl, $m)){
		//	$sub_dir = $m[1] . "/";
		//}
		//$nc2BaseUrl = preg_quote($nc2BaseUrl, '/');
		$nc2BaseUrlHttp = preg_quote($nc2BaseUrlHttp, '/');
		$nc2BaseUrlHttps = preg_quote($nc2BaseUrlHttps, '/');

		// save〇〇に渡すデータを、WysiwygBehavior::REPLACE_BASE_URL（{{__BASE_URL__}}）にすると、
		// HTMLPurifierで除去される（詳細箇所については未調査）
		// なので、WysiwygBehavior::beforeSave で置換される文字列にしとく
		// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/Model/Behavior/WysiwygBehavior.php#L83
		//$replace = WysiwygBehavior::REPLACE_BASE_URL . './?action=common_download_main&upload_id=';
		//
		// sub_dirは、コンソールから移行ツールを起動する時のオプション --nc3base にディレクトリをセットすると
		// Router::url('/', true) に反映されるのため、別途$sub_dirをつける処理は必要なかった。
		//$replaceUrl = Router::url('/', true). $sub_dir;
		$replaceUrl = Router::url('/', true);

		// @see https://regexper.com/#%2F%28src%7Chref%29%3D%5B%22%5C'%5D%28http%3A%5C%2F%5C%2Flocalhost%5C%2F%7Chttps%3A%5C%2F%5C%2Flocalhost%5C%2F%7C%5C.%5C%2F%7Chttp%3A%5C%2F%5C%2Flocalhost%5C%2F%28%3F!.*%22%29%5C%2F%7Chttps%3A%5C%2F%5C%2Flocalhost%5C%2F%28%3F!.*%22%29%5C%2F%29%28%5C%3F%7C%5B%5Cw%5Cd%5C%2F%25%23%24%26%28%29~_.%3D%2B%5C-%E3%81%81-%E3%82%93%E3%82%A1-%E3%83%B6%E3%83%BC%E4%B8%80-%E9%BE%A0%EF%BC%90-%EF%BC%99%E3%80%81%E3%80%82%5D%2B%5B%5C%3F%5D%2B%29action%3Dcommon_download_main%26%28%3F%3Aamp%3B%29%3Fupload_id%3D%28%5Cd%2B%29%5B%22%5C'%5D%2F
		// ・BaseURLのディレクトリ型対応追加
		// ・・ディレクトリ型対応バグ修正 \/.*?\/ (ダブルクォート含む全文字0文字以上。これだとコンテンツに２つ<a>タグあるとバグった) → \/(?!.*")\/ (ダブルクォート以外の0文字以上)
		// ・下記のようなURL（ひらがなカタカナ漢字）でも対応
		//   http://localhost/9th/sanka/?action=common_download_main&amp;upload_id=90
		//   http://localhost/16th/大会長より/?action=common_download_main&upload_id=139
		// ・シングルクォート囲みにも対応 src=""|src=''|herf=""|herf=''
		// ・NC2のコンテンツにhttp://nc2BaseUrl/, https://nc2BaseUrl/混在でも変換に対応
		//$pattern = '/(src|href)="(' . $nc2BaseUrl . '\/|\.\/)(\?|index\.php\?)action=common_download_main&(?:amp;)?upload_id=(\d+)"/';
		//$pattern = '/(src|href)="(' . $nc2BaseUrl . '\/|\.\/|' . $nc2BaseUrl . '\/.*?\/)(\?|index\.php\?)action=common_download_main&(?:amp;)?upload_id=(\d+)"/';
		//$pattern = '/(src|href)="(' . $nc2BaseUrlHttp . '\/|' . $nc2BaseUrlHttps . '\/|\.\/|' . $nc2BaseUrlHttp . '\/.*?\/|' . $nc2BaseUrlHttps . '\/.*?\/)(\?|[\w\d\/%#$&()~_.=+\-ぁ-んァ-ヶー一-龠０-９、。]+[\?]+)action=common_download_main&(?:amp;)?upload_id=(\d+)"/';
		$pattern = '/(src|href)=["\'](\.\/|' . $nc2BaseUrlHttp . '\/|' . $nc2BaseUrlHttps . '\/|' . $nc2BaseUrlHttp . '\/(?!.*")\/|' . $nc2BaseUrlHttps . '\/(?!.*")\/)(\?|[0-9a-zA-Z\/%#\$&\(\)~_\.=\+\-ぁ-んァ-ヶー一-龠０-９、。]+[\?]+)action=common_download_main&(?:amp;)?upload_id=([0-9]+)["\']/';

		preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$nc3UploadFile = $this->__saveUploadFileFromNc2($match[4]);
			//CakeLog::debug(var_export($match, true));
			if (!$nc3UploadFile) {
				// エラー処理どうする？とりあえず継続しとく。
				continue;
			}

			//画像へのリンクの場合にはhrefの続きでaction=common_download_mainがある場合がある
			if ($match[1] === 'href') {
				if(preg_match('/href.*?(src=\".*?")/', $match[0], $hrefImgMatch)){
					$match[0] = $hrefImgMatch[1];
					$match[1] = 'src';
				}
			}

			// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/Controller/WysiwygFileController.php#L107
			// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/Config/routes.php#L11-L19
			$controller = 'file';
			$size = '';
			$class = '';
			if ($match[1] === 'src') {
				$controller = 'image';
				$size = '/' . $this->__getImageSize($nc3UploadFile);
				$class = 'class="img-responsive nc3-img nc3-img-block" ';
			}

			$strReplaceArguments[0][] = $match[0];

			$strReplaceArguments[1][] = $class . $match[1] . '="' .
				$replaceUrl . 'wysiwyg/' . $controller . '/download' .
				'/' . $nc3UploadFile['UploadFile']['room_id'] .
				'/' . $nc3UploadFile['UploadFile']['id'] .
				$size . '"';
		}

		//onmouseout, onmouseover, oncontextmenuの対応 一旦空にする
		$onPattern = '/(onmouseover|onmouseout|oncontextmenu)=".*?"/';
		preg_match_all($onPattern, $content, $onMatches, PREG_SET_ORDER);
		foreach ($onMatches as $onMatch) {
			$strReplaceArguments[0][] = $onMatch[0];
			$strReplaceArguments[1][] = "";
		}

		return $strReplaceArguments;
	}

/**
 * Save UploadFile from Nc2.
 *
 * @param string $nc2UploadId Nc2Upload.id.
 * @return array Nc3UploadFile data.
 */
	private function __saveUploadFileFromNc2($nc2UploadId) {
		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $UploadFile UploadFile */
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$UploadFile = ClassRegistry::init('Files.UploadFile');
		$mapIdList = $Nc2ToNc3Map->getMapIdList('UploadFile', $nc2UploadId);
		if ($mapIdList) {
			/* アップロードファイルの重複ファイルを検知する為にログを出力 */
			$this->_writeMigrationLog(__d('nc2_to_nc3', 'Exist Fire. Nc2UploadId : '.$nc2UploadId));
			return $UploadFile->findById($mapIdList[$nc2UploadId], null, null, -1);
		}

		/* @var $nc2ToNc3Upload Nc2ToNc3Upload */
		$nc2ToNc3Upload = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Upload');
		$fileData = $nc2ToNc3Upload->generateUploadFile($nc2UploadId);
		if (!$fileData) {
			return $fileData;
		}

		/* @var $Nc2ToNc3Room Nc2ToNc3Room */
		$Nc2ToNc3Room = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Room');
		$nc2Upload = $nc2ToNc3Upload->getNc2UploadByUploadId($nc2UploadId);
		$roomMap = $Nc2ToNc3Room->getMap($nc2Upload['Nc2Upload']['room_id']);

		// Room.idの書き換え
		// @see https://github.com/NetCommons3/Files/blob/3.1.0/Model/UploadFile.php#L174-L176
		$nc3UploadFile['UploadFile'] = [
			'room_id' => $roomMap['Room']['id']
		];
		$contentRoomId = Current::read('Room.id');
		Current::write('Room.id', $roomMap['Room']['id']);

		// 移行時の画像サイズは原寸のため、サムネイルは作成せず原寸のみにする。 add by mutaguchi@opensource-workshop.jp
		$UploadFile->uploadSettings('real_file_name', 'thumbnailSizes', []);

		// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/Controller/WysiwygFileController.php#L88
		// @see https://github.com/NetCommons3/Files/blob/3.1.0/Model/UploadFile.php#L260-L263
		$CakeFile = new File($fileData['tmp_name']);

		// ウィジウィグのNC2アップロード時ファイル名を移行する add by mutaguchi@opensource-workshop.jp
		$CakeFile->originalName = $nc2Upload['Nc2Upload']['file_name'];

		$data = $UploadFile->registByFile($CakeFile, 'wysiwyg', null, 'Wysiwyg.file', $nc3UploadFile);
		// Room.idを戻す
		Current::write('Room.id', $contentRoomId);
		if (!$data) {
			$message = __d('nc2_to_nc3', '%s not found .', 'Nc2Upload:' . $nc2UploadId);
			$this->_writeMigrationLog($message);

			return $data;
		}

		$idMap = [
			$nc2UploadId => $UploadFile->id
		];
		$this->_saveMap('UploadFile', $idMap);

		return $data;
	}

/**
 * Get image size.
 *
 * @param array $nc3UploadFile Nc3UploadFile data.
 * @return string image size('big','medium','small','thumb')
 */
	private function __getImageSize($nc3UploadFile) {
		/* @var $UploadFile UploadFile */
		/*
		$UploadFile = ClassRegistry::init('Files.UploadFile');
		$path = $UploadFile->getRealFilePath($nc3UploadFile);
		list($width, $height) = getimagesize($path);

		if ($width <= 80 && $height <= 80) {
			return 'thumb';
		}
		if ($width <= 200 && $height <= 200) {
			return 'small';
		}
		if ($width <= 400 && $height <= 400) {
			return 'medium';
		}
		*/

		return '';
	}

/**
 * Get str_replace arguments of title icon.
 *
 * @param string $content Nc2 content.
 * @return array str_replace arguments.(0:$search,1:$replace)
 */
	private function __getStrReplaceArgumentsOfTitleIcon($content) {
		$strReplaceArguments = [];

		// PCRE_UNGREEDYパターン修飾子と.*?のどっちが良いのかわからんので、とりあえず.*?
		// あと、style属性はそのままにしとく。
		$pattern = '/src=".*?\/images\/comp\/textarea\/((?:titleicon|smiley)\/.*?\.gif)"/';

		// src属性のURLに Router::url('/') を使用している。
		// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/View/Helper/WysiwygHelper.php#L92
		// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/View/Helper/WysiwygHelper.php#L152-L162
		// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/webroot/js/plugins/titleicons/plugin.js#L25-L32
		// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/webroot/js/plugins/titleicons/plugin.js#L52-L56
		$prefixPath = $this->__getSubDirectory();

		preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {

			//画像の後にアイコンがある場合
			if(preg_match('/common_download_main.*?<img.*?(src=\".*?\")/', $match[0], $downloadMatch)){
				if(!preg_match($pattern, $downloadMatch[0], $downloadMatch2)){
					continue;
				}
				$match[0] = $downloadMatch[1];
			}

			$strReplaceArguments[0][] = $match[0];

			// class属性は最後になっているが、挿入された結果が先頭になっているので、src属性の前に設定
			// @see https://github.com/NetCommons3/Wysiwyg/blob/3.1.0/webroot/js/plugins/titleicons/plugin.js#L55
			$strReplaceArguments[1][] = 'class="nc-title-icon" src=' .
				'"' . $this->_convertTitleIcon($match[1], $prefixPath) . '"';
		}

		return $strReplaceArguments;
	}

/**
 * Get sub directory
 *
 * @return string sub directory
 */
	private function __getSubDirectory() {
		/* @var $RequestObject CakeRequest */
		$RequestObject = Router::getRequest();

		if (!$RequestObject->requested) {
			// Consoleから CakeObject::requestAction で呼び出しているので、CakeRequest::requested で判断
			// Consoleで呼び出される判断方法はほかにあるかも。
			return Router::url('/');
		}

		// Consoleで実行すると Router::url('/') で問題発生
		// @see Nc2ToNc3Shell::main
		return Router::url('/');
	}

/**
 * Get str_replace arguments of page link.
 *
 * @param string $content Nc2 content.
 * @return array str_replace arguments.(0:$search,1:$replace)
 */
	private function __getStrReplaceArgumentsOfBaseUrlLink($content) {
		$strReplaceArguments = [];

		/* @var $Nc2ToNc3 Nc2ToNc3 */
		/* @var $Nc2ToNc3Page Nc2ToNc3Page */
		$Nc2ToNc3 = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3');
		$Nc2ToNc3Page = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Page');

		$nc2BaseUrl = Hash::get($Nc2ToNc3->data, ['Nc2ToNc3', 'base_url']);
		// http, https混在コンテンツに対応 by mutaguchi@opensource-workshop.jp
		$nc2BaseUrlParse = parse_url($nc2BaseUrl);
		$nc2BaseUrlHttp = 'http://' . $nc2BaseUrlParse['host'];
		$nc2BaseUrlHttps = 'https://' . $nc2BaseUrlParse['host'];
		//$sub_dir = "";
		//if(preg_match("/.*?\:\/\/.*?\/(.*$)/",$nc2BaseUrl, $m)){
		//	$sub_dir = $m[1]."/";
		//}
		//$nc2BaseUrl = preg_quote($nc2BaseUrl, '/');
		$nc2BaseUrlHttp = preg_quote($nc2BaseUrlHttp, '/');
		$nc2BaseUrlHttps = preg_quote($nc2BaseUrlHttps, '/');

		// sub_dirは、コンソールから移行ツールを起動する時のオプション --nc3base にディレクトリをセットすると
		// Router::url('/', true) に反映されるのため、別途$sub_dirをつける処理は必要なかった。
		//$replaceBaseUrl = Router::url('/', true). $sub_dir;
		$replaceBaseUrl = Router::url('/', true);

		// ・BaseURLのディレクトリ型対応追加
		// ・シングルクォート囲みにも対応 src=""|src=''|herf=""|herf=''
		// ・NC2のコンテンツにhttp://nc2BaseUrl/, https://nc2BaseUrl/混在でも変換に対応
		//$pattern = '/href="(' . $nc2BaseUrl . '\/|\.\/|' . $nc2BaseUrl . '\/.*?\/)(.*?)"/';
		//$pattern = '/href="(' . $nc2BaseUrlHttp . '\/|' . $nc2BaseUrlHttps . '\/|\.\/|' . $nc2BaseUrlHttp . '\/.*?\/|' . $nc2BaseUrlHttps . '\/.*?\/)(.*?)"/';
		$pattern = '/href=["\'](' . $nc2BaseUrlHttp . '\/|' . $nc2BaseUrlHttps . '\/|\.\/|' . $nc2BaseUrlHttp . '\/(?!.*")\/|' . $nc2BaseUrlHttps . '\/(?!.*")\/)(.*?)["\']/';
		preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$replacePath = $match[2];

			//実行順序を入れ替えたので、アップロードファイルへのリンクは次のタスクで実行する
			preg_match('/action=common_download_main/', $replacePath, $uploadIdMatches);
			if ($uploadIdMatches) {
				continue;
			}

			preg_match('/page_id=(\d+)/', $replacePath, $pageIdMatches);
			if ($pageIdMatches) {
				$pageMap = $Nc2ToNc3Page->getMap($pageIdMatches[1]);
				$replacePath = $pageMap['Page']['permalink'];
			}

			$strReplaceArguments[0][] = $match[0];
			$strReplaceArguments[1][] = 'href="' . $replaceBaseUrl . $replacePath . '"';
		}

		return $strReplaceArguments;
	}

/**
 * Get str_replace arguments of cabinet file.
 *
 * @param string $content Nc2 content.
 * @return array str_replace arguments.(0:$search,1:$replace)
 */
	private function __getStrReplaceArgumentsOfCabinetFile($content) {
		$strReplaceArguments = [];

		// cabinet_action_main_downloadアクションの処理
		// 以下、NC1からの移行処理を参考
		/*
		 $cabinet =& Cabinet::getInstance();
		 $block =& Block::getInstance();
		 $replace1 = './?action=pages_view_main&active_action=cabinet_view_main_init&folder_id=';
		 $replace2 = '&block_id=';
		 $replace3 = '#_';
		 $pattern = '/["\']&XOOPS_URL;\/modules\/cabinet\/cabinet_main\.php\?block_id=(\S*)&folder_id=(\S*)#(\S*)["\']/';
		 preg_match_all($pattern, $str, $matches, PREG_SET_ORDER);
		 foreach ($matches as $match) {
		 $replace = '';
		 $lastCharacter = substr($match[3], -1);
		 if ($lastCharacter == '"' || $lastCharacter == '\'') {
		 $match[0] = substr($match[0], 0, -1);
		 $match[3] = substr($match[3], 0, -1);
		 }

		 if (in_array($match[0], $searches)) {
		 continue;
		 }

		 $searches[] = $match[0];

		 $match[1] = urldecode($match[1]);
		 $match[2] = urldecode($match[2]);
		 $match[3] = urldecode($match[3]);

		 $cabinetAssociation = $cabinet->getAssociation($match[2]);
		 $blockAssociation = $block->getAssociation($match[1]);
		 if (empty($cabinetAssociation) || empty($blockAssociation)) {
		 $replace = '"' . $replace1 . $replace2 . $replace3 . '"';
		 } else {
		 $replace = '"' . $replace1 . $cabinetAssociation['fileID']
		 . $replace2 . $blockAssociation['blockID']
		 . $replace3 . $blockAssociation['blockID'] . '"';
		 }

		 $replaces[] = $replace;
		 }

		 $searches[] = '&XOOPS_URL;/include/comp/textarea/tex.php?';
		 $replaces[] = './?action=common_tex_main&';

		 $searches[] = '&XOOPS_URL;';
		 $replaces[] = './nc1Files';

		 $str = str_replace($searches, $replaces, $str);

		 return $str;

		 $body = $content;

		 return $body;*/

		return $strReplaceArguments;
	}

/**
 * Get str_replace arguments of TeX.
 *
 * @param string $content Nc2 content.
 * @return array str_replace arguments.(0:$search,1:$replace)
 */
	private function __getStrReplaceArgumentsOfTex($content) {
		$strReplaceArguments = [];

		$pattern = '/<img .*? src=".*?action=common_tex_main&amp;c=(.*?)" .*?\/>/';
		preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$strReplaceArguments[0][] = $match[0];

			$texValue = str_replace("%_", "%", $match[1]);
			$texValue = rawurldecode($texValue);

			$strReplaceArguments[1][] =
				'<span class="tex-char">' .
				'$$' . $texValue . '$$' .
				'</span>';
		}

		return $strReplaceArguments;
	}

/**
 * Get str_replace arguments of Table.
 *
 * @param string $content Nc2 content.
 * @return array str_replace arguments.(0:$search,1:$replace)
 */
	private function __getStrReplaceArgumentsOfTable($content) {
		$strReplaceArguments = [];

		// <table>のstyleをclassに置き換え
		$patterns[] = [
			'pattern' => '/<table.*? (style=".*?")>/',
			'replace' => 'class="table table-bordered table-responsive"'
		];
		// <tr><td>のstyle消す
		//		$patterns[] = [
		//			'pattern' => '/<tr.*?( style=".*?")>/',
		//			'replace' => ''
		//		];
		//		$patterns[] = [
		//			'pattern' => '/<td.*?( style=".*?")>/',
		//			'replace' => ''
		//		];
		foreach ($patterns as $pattern) {
			preg_match_all($pattern['pattern'], $content, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {

				$replaceTable = str_replace($match[1], $pattern['replace'], $match[0]);
				$strReplaceArguments[0][] = $match[0];
				$strReplaceArguments[1][] = $replaceTable;
			}
		}

		return $strReplaceArguments;
	}

}
