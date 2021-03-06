<?php
/**
 * Nc2ToNc3
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3AppModel', 'Nc2ToNc3.Model');

/**
 * Nc2ToNc3
 *
 * Data array sample
 * $data['database'] => 'nc2421'
 * $data['prefix'] => 'nc_'
 * $data['datasource'] => 'Database/Mysql'
 * $data['host'] => 'localhost'
 * $data['port'] => '3306'
 * $data['login'] => ''
 * $data['password'] => ''
 * $data['persistent'] => ''
 * $data['encoding'] => 'utf8'
 * $data['schema'] => 'public'
 * $data['upload_path'] => ''
 * $data['items_ini_path'] => ''
 *
 * @see Nc2ToNc3BaseBehavior
 * @method void writeMigrationLog($message)
 * @method Model getNc2Model($tableName)
 */
class Nc2ToNc3 extends Nc2ToNc3AppModel {

/**
 * この実行時間(秒)を越えたらClassRegistry::flush()
 * 実行したら、お知らせ等の移行でupload_idなしのエラーが出たため、ClassRegistry::flushさせない数字にする。
 * [エラー内容]
 * Nc2Upload upload_id:999 not found . - announcement/9999.png : Nc2ToNc3Upload on line 88
 *
 * @var float
 */
	public $executionFlushTime = 99999999;

/**
 * The DataSource name for nc2
 *
 * @var string
 */
	const CONNECTION_NAME = 'nc2';

/**
 * The DataSource name for nc2
 *
 * @var string
 */
	const VALID_VERSION = '2.4.2.1';

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
	public $actsAs = ['Nc2ToNc3.Nc2ToNc3Base'];

/**
 * Called during validation operations, before validation. Please note that custom
 * validation rules can be defined in $validate.
 *
 * @param array $options Options passed from Model::save().
 * @return bool True if validate operation should continue, false to abort
 * @link http://book.cakephp.org/2.0/en/models/callback-methods.html#beforevalidate
 * @see Model::save()
 */
	public function beforeValidate($options = array()) {
		$this->validate = ValidateMerge::merge(
			$this->validate,
			[
				'database' => [
					'notBlank' => [
						'rule' => ['notBlank'],
						'message' => sprintf(
							__d('net_commons', 'Please input %s.'), __d('nc2_to_nc3', 'Database')
						),
						'allowEmpty' => false,
						'required' => true,
					],
					'canConnectNc2' => [
						'rule' => ['canConnectNc2'],
						// 'message' => canConnectNc2でException::getMessage()を返す
					],
					'isValidNc2Data' => [
						'rule' => ['isValidNc2Data'],
						// 'message' => isValidNc2Dataでメッセージを返す
					],
					// TODOーNC3のバージョン、状態（サイト閉鎖）をチェック
				],
				'upload_path' => [
					'notBlank' => [
						'rule' => ['notBlank'],
						'message' => sprintf(
							__d('net_commons', 'Please input %s.'), __d('nc2_to_nc3', 'Upload file path')
						),
						'allowEmpty' => false,
						'required' => true,
					],
					'isUploadPath' => [
						'rule' => ['isUploadPath'],
						'message' => sprintf(
							__d('nc2_to_nc3', 'The above Upload file path does not exist.')
						),
					]
				],
				'base_url' => [
					'notBlank' => [
						'rule' => ['notBlank'],
						'message' => sprintf(
							__d('net_commons', 'Please input %s.'), __d('nc2_to_nc3', 'Base url') . "\n" .
							__d('nc2_to_nc3', 'Input URL of NetCommons2 for converting link in WYSIWYG content.')
						),
						'allowEmpty' => false,
						'required' => true,
					],
					'url' => [
						'rule' => ['url'],
						'message' => sprintf(
							__d('net_commons', 'Unauthorized pattern for %s. Please input the data in %s format.'),
							__d('net_commons', 'URL'),
							__d('net_commons', 'URL')
						),
					],
				],
			]
		);

		return parent::beforeValidate($options);
	}

/**
 * Validate to create a DataSource object for nc2
 *
 * @return string|bool True on it access to nc2 database
 */
	public function canConnectNc2() {
		$config = $this->data['Nc2ToNc3'];
		$connectionObjects = ConnectionManager::enumConnectionObjects();
		$nc3config = $connectionObjects['master'];
		$config += $nc3config;
		$config['datasource'] = 'Nc2ToNc3.Database/Nc2Mysql';

		// DataSource情報が間違っている場合、Exception が発生するのでハンドリングできない
		// Try{}catch{}やってみた。
		try {
			ConnectionManager::create(static::CONNECTION_NAME, $config);
		} catch (Exception $ex) {
			CakeLog::error($ex);
			return $ex->getMessage();
		}
		return true;
	}

/**
 * Validate DataSource object for nc2.
 *
 * @return string|bool True on it access to config table of nc2.
 */
	public function isValidNc2Data() {
		/* @var $Nc2Config AppModel */
		$Nc2Config = $this->getNc2Model('config');

		// DataSource情報(prefix)が間違っている場合、Exception が発生するのでハンドリングできない
		// Try{}catch{}やってみた。
		try {
			// 対象バージョンチェック
			$configData = $Nc2Config->findByConfName('version', 'conf_value', null, -1);
			if ($configData['Nc2Config']['conf_value'] != static::VALID_VERSION) {
				ConnectionManager::drop(static::CONNECTION_NAME);
				return __d('nc2_to_nc3', 'NetCommons2 version is not %s', static::VALID_VERSION);
			}

			// サイト閉鎖チェックはダンプデータをインポートしたDBを考慮するとしない方が良いのでは？
			// 運用中のDBを対象にしないことを推奨する
			//$configData = $Nc2Config->findByConfName('closesite', 'conf_value', null, -1);

		} catch (Exception $ex) {
			CakeLog::error($ex);
			return __d('nc2_to_nc3', 'NetCommons2 table is not found.');
		}

		return true;
	}

/**
 * Initializes the model for writing a new record, loading the default values
 * for those fields that are not defined in $data, and clearing previous validation errors.
 * Especially helpful for saving data in loops.
 * Not call parent::create, so the parameter is unnecessary.
 *
 * @param bool|array $data Optional data array to assign to the model after it is created. If null or false,
 *   schema data defaults are not merged.
 * @param bool $filterKey If true, overwrites any primary key input with an empty value
 * @return array The current Model::data; defaults from NetCommons3 DataSource
 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
 */
	public function create($data = array(), $filterKey = false) {
		$connectionObjects = ConnectionManager::enumConnectionObjects();
		$nc3config = $connectionObjects['master'];
		unset($nc3config['database'], $nc3config['prefix']);
		$nc3config['base_url'] = 'http://';

		// TODOー開発用データ
		$nc3config['database'] = 'netcommons';
		$nc3config['prefix'] = 'nc_';
		$nc3config['upload_path'] = '/var/www/html/NC2/webapp/uploads/';

		return $nc3config;
	}

/**
 * Migration
 *
 * @param array $data received post data
 * @return bool True on success
 */
	public function migration($data) {
		// 移行ツールは、コントローラ経由で当メソッド（migration）を呼び出してるため、システム設定＞サーバ設定＞PHP最大メモリ数(初期値:128M)
		// がセットされる。バッチ実行がメインのため、ここでメモリを無制限に設定
		ini_set('memory_limit', '-1');

		$this->set($data);

		if (!$this->validates()) {
			return false;
		}

		// 実行時間の計測開始時間
		/* @see Nc2ToNc3BaseBehavior::executionTimeStart() */
		$timeStart = $this->executionTimeStart();

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Migration start.'));

		// phpmdのNPath complexity threshold is 200 に対応するように機能ごとにループ
		$migrationModelNames = [
			'Nc2ToNc3UserAttribute',
			'Nc2ToNc3User',
			'Nc2ToNc3Room',
			'Nc2ToNc3Page',
			'Nc2ToNc3Frame',
			'Nc2ToNc3AccessCounter',
			'Nc2ToNc3Announcement',
			'Nc2ToNc3Bbs',
			'Nc2ToNc3Blog',
			'Nc2ToNc3Cabinet',
			'Nc2ToNc3Calendar',
			'Nc2ToNc3CircularNotice',
			'Nc2ToNc3Faq',
			'Nc2ToNc3Iframe',
			'Nc2ToNc3Link',
			'Nc2ToNc3Menu',
			'Nc2ToNc3PhotoAlbum',
			'Nc2ToNc3Registration',
			'Nc2ToNc3RssReader',
			'Nc2ToNc3Questionnaire',
			'Nc2ToNc3Quiz',
			'Nc2ToNc3Search',
			'Nc2ToNc3Task',
			'Nc2ToNc3Video',
			'Nc2ToNc3Topic',
			'Nc2ToNc3Reservation',
			'Nc2ToNc3Multidatabase',
		];

		// Nc2のテーブル存在チェック
		$checkNc2ModelNames = [
			//以下に対応するkeyとNc2のテーブル名をセットする
			'Nc2ToNc3Bbs'=>'bbs',
			'Nc2ToNc3Iframe'=>'iframe',
			'Nc2ToNc3Questionnaire'=>'questionnaire',
			'Nc2ToNc3Quiz'=>'questionnaire',
			'Nc2ToNc3Task'=>'todo',
			'Nc2ToNc3Reservation'=>'reservation',
			'Nc2ToNc3Multidatabase'=>'multidatabase',
		];
		foreach ($checkNc2ModelNames as $nc3Name => $nc2TblName){
			try {
				$Nc2Tbl = $this->getNc2Model($nc2TblName);
				$nc2Tbls = $Nc2Tbl->hasAny();
			} catch (Exception $ex) {
				if(($key = array_search($nc3Name, $migrationModelNames)) !== false) {
					unset($migrationModelNames[$key]);
				}
			}
		}

		$excludePlugins = explode(',', $this->data['Nc2ToNc3']['exclude']);
		foreach ($migrationModelNames as $migrationModelName) {
			if (in_array(substr($migrationModelName, 8), $excludePlugins, true)) {
				continue;
			}
			// 実行時間の計測開始時間
			/* @see Nc2ToNc3BaseBehavior::executionTimeStart() */
			$timeModelStart = $this->executionTimeStart();

			$migrationModelName = 'Nc2ToNc3.' . $migrationModelName;

			/* @var $MigrationModel Nc2ToNc3UserAttribute */
			/* @var $MigrationModel Nc2ToNc3User */
			/* @var $MigrationModel Nc2ToNc3Room */
			/* @var $MigrationModel Nc2ToNc3Page */
			/* @var $MigrationModel Nc2ToNc3Frame */
			/* @var $MigrationModel Nc2ToNc3Announcement */
			$MigrationModel = ClassRegistry::init($migrationModelName);
			if (!$MigrationModel->migrate()) {
				$this->validationErrors = $MigrationModel->validationErrors;
				return false;
			}

			// @see Nc2ToNc3UserAttribute::calledCakeMigration
			if ($migrationModelName == 'Nc2ToNc3.Nc2ToNc3UserAttribute' &&
				$MigrationModel->calledCakeMigration) {
				ClassRegistry::addObject('Nc2ToNc3', $this);
			}

			// ClassRegistryが移行で各プラグインのモデルのsave系を実行すると、処理が遅くなる & メモリ食うため、
			// migrationModelが切り替わるタイミングで、いったん初期化する。
			//ClassRegistry::flush();
			/* @see Nc2ToNc3BaseBehavior::executionTimeEnd() */
			$this->executionTimeEnd(__METHOD__ . ' ' . $migrationModelName, $timeModelStart,
				$this->executionFlushTime, true);
		}

		// 移行共通後処理
		$CommonAfterModel = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3CommonAfter');
		if (!$CommonAfterModel->migrate()) {
			$this->validationErrors = $CommonAfterModel->validationErrors;
			return false;
		}

		$this->writeMigrationLog(__d('nc2_to_nc3', 'Migration end.'));
		$this->executionTimeEnd(__METHOD__ , $timeStart, $this->executionFlushTime, true);

		return true;
	}

/**
 * Validate Upload Path for nc2.
 *
 * @return string|bool True on it access to config table of nc2.
 */
	public function isUploadPath() {
		$config = $this->data['Nc2ToNc3'];
		$nc2UploadPath = $config['upload_path'];

		return is_dir($nc2UploadPath);
	}
}

