<?php

/*
 * AdobeConnect 8 api client,
 * fork by Jesse Keane
 * @see https://github.com/jarofghosts/AdobeConnect-php-api-client
 * @see http://help.adobe.com/en_US/connect/8.0/webservices/index.html
 * @version 1
 *
 * Copyright 2012, sc0rp10
 * https://weblab.pro
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 *
 */

class Connect
{

	/**
	 * @const
	 * adobe connect username
	 */

	const USERNAME = '';

	/**
	 * @const
	 * adobe connect password
	 */
	const PASSWORD = '';

	/**
	 * @const
	 *  personal api URL
	 */
	const BASE_DOMAIN = '';

	/**
	 * @const
	 * personal root folder id
	 * @see http://forums.adobe.com/message/2620180#2620180
	 */
	const ROOT_FOLDER_ID = 0; //root folder id

	/**
	 * @var string filepath to cookie-jar file
	 */

	private $cookie;

	/**
	 * @var resource
	 */
	private $curl;

	/**
	 * @var bool
	 */
	private $is_authorized = false;

	/**
	 *
	 * @var string 
	 */
	private $cookie_string;

	/**
	 *
	 */
	public function __construct() {
		$this->cookie = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cookie_' . time() . '.txt';
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt( $ch, CURLOPT_REFERER, self::BASE_DOMAIN );
		curl_setopt( $ch, CURLOPT_COOKIEJAR, $this->cookie );
		curl_setopt( $ch, CURLOPT_COOKIEFILE, $this->cookie );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, true );
		$this->curl = $ch;

		$this->makeAuth();

	}

	/**
	 * make auth-request with username/password (defaults to stored)
	 *
	 * @param array
	 * 
	 * @return AdobeConnectClient
	 */
	public function makeAuth( $login_array = array(
		'login' => self::USERNAME,
		'password' => self::PASSWORD
	) ) {
		$login_array['external-auth'] = 'use';
		$this->makeRequest( 'login', $login_array );

		$this->is_authorized = true;

		return $this;

	}

	/**
	 * get common info about current user
	 *
	 * @return array
	 */
	public function getCommonInfo() {
		return $this->makeRequest( 'common-info' );

	}
	/**
	 * change user to perform all actions afterwards
	 * 
	 * @param array $credentials
	 */
	public function asUser( $credentials = array(
		'login' => self::USERNAME,
		'password' => self::PASSWORD ) ) {

		$this->authorized = false;
		$this->makeAuth( $credentials );

	}

	/**
	 * create user
	 *
	 * @param string $email
	 * @param string $password
	 * @param string $first_name
	 * @param string $last_name
	 * @param string $type
	 *
	 * @return array
	 */
	public function createUser( $email, $password, $first_name, $last_name, $type = 'user' ) {
		$result = $this->makeRequest( 'principal-update', array(
			'first-name' => $first_name,
			'last-name' => $last_name,
			'email' => $email,
			'password' => $password,
			'type' => $type,
			'has-children' => 0
				)
		);

		return $result;

	}

	/**
	 *  read cookie string from current session, useful for external auth
	 * 
	 * @return string
	 */
	public function getCookie() {
		return $this->cookie_string;

	}

	/**
	 * @param string $email
	 * @param bool   $only_id
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 *
	 */
	public function getUserByEmail( $email, $only_id = false ) {
		$result = $this->makeRequest( 'principal-list', array(
			'filter-email' => $email
				)
		);
		if ( empty( $result['principal-list'] ) ) {
			throw new Exception( 'Cannot find user' );
		}
		if ( $only_id ) {
			return $result['principal-list']['principal']['@attributes']['principal-id'];
		}
		return $result;

	}

	/**
	 * update user fields
	 *
	 * @param string $email
	 * @param array  $data
	 *
	 * @return mixed
	 */
	public function updateUser( $email, array $data = array( ) ) {
		$principal_id = $this->getUserByEmail( $email, true );
		$data['principal-id'] = $principal_id;
		return $this->makeRequest( 'principal-update', $data );

	}

	/**
	 * get all users list
	 *
	 * @return array
	 */
	public function getUsersList() {
		$users = $this->makeRequest( 'principal-list' );
		$result = array( );
		foreach ( $users['principal-list']['principal'] as $key => $value ) {
			$result[$key] = $value['@attributes'] + $value;
		};
		unset( $result[$key]['@attributes'] );
		return $result;

	}

	/**
	 * get all meetings
	 *
	 * @return array
	 */public function getAllMeetings( $show_all = true ) {

		$params = $show_all ? array( ) : array( 'filter-expired' => 'false' );
		return $this->makeRequest( 'report-my-meetings', $params );

	}

	/**
	 * get base for concatenation in embedding
	 * 
	 * @return string
	 */
	public function getUrlBase() {

		return substr(self::BASE_DOMAIN, 0, -5);

	}

	/**
	 * get SCO information from ID
	 * 
	 * @param string $sco_id
	 * 
	 * @return array
	 */public function getUrl( $sco_id ) {

		$result = $this->makeRequest( 'sco-info', array(
			'sco-id' => $sco_id
				) );
		return $result['sco']['url-path'];

	}

	/**
	 * create meeting-folder
	 *
	 * @param string $name
	 * @param string $url
	 *
	 * @return array
	 */
	public function createFolder( $name, $parent_folder = null ) {

		$parent_folder = $parent_folder ? : self::FOLDER_ID;

		$result = $this->makeRequest( 'sco-update', array(
			'type' => 'folder',
			'name' => $name,
			'folder-id' => $parent_folder,
			'depth' => 1
				)
		);
		return $result['sco']['@attributes']['sco-id'];

	}

	/**
	 * Check for a folder's existence.
	 * 
	 * @param string $search
	 */
	public function checkFolder( $search ) {
		$results = $this->makeRequest( 'sco-search-by-field', array(
			'query' => $search,
			'filter-type' => 'folder',
			'field' => 'name'
				) );

		return ( isset( $results['sco-search-by-field-info'] ) && count( $results['sco-search-by-field-info'] ) > 0);

	}

	/**
	 * create a new meeting
	 *
	 * @param int    $folder_id
	 * @param string $name
	 * @param string $date_begin
	 * @param string $date_end
	 * @param string $url
	 *
	 * @return string
	 */
	public function createMeeting( $folder_id, $name, $description, $date_begin, $date_end ) {

		$result = $this->makeRequest( 'sco-update', array(
			'type' => 'meeting',
			'name' => $name,
			'description' => $description,
			'folder-id' => $folder_id,
			'date-begin' => date( 'Y-m-d\TH:i:sO', strtotime( $date_begin ) ),
			'date-end' => date( 'Y-m-d\TH:i:sO', strtotime( $date_end ) )
				)
		);
		return $result['sco']['@attributes']['sco-id'];

	}

	/**
	 * update meeting_id with new name, description and date_begin/date_end,
	 * note: date will attempt to auto-format to ISO-8601 as per the connect api standard using php's strtotime
	 * 
	 * @param int $meeting_id
	 * @param string $name
	 * @param string $description
	 * @param string $date_begin
	 * @param string $date_end
	 * @return string
	 */
	public function updateMeeting( $meeting_id, $name, $description, $date_begin, $date_end ) {

		$result = $this->makeRequest( 'sco-update', array(
			'type' => 'meeting',
			'name' => $name,
			'description' => $description,
			'sco-id' => $meeting_id,
			'date-begin' => date( 'Y-m-d\TH:i:sO', strtotime( $date_begin ) ),
			'date-end' => date( 'Y-m-d\TH:i:sO', strtotime( $date_end ) )
				)
		);
		return $result['sco']['@attributes']['sco-id'];

	}

	/**
	 * invite user to meeting
	 *
	 * @param int    $meeting_id
	 * @param string $email
	 *
	 * @return mixed
	 */
	public function addUserToMeeting( $meeting_id, $user_id ) {

		$result = $this->makeRequest( 'permissions-update', array(
			'principal-id' => $user_id,
			'acl-id' => $meeting_id,
			'permission-id' => 'view'
				)
		);

		return $result;

	}

	/**
	 * revoke a user's access to a meeting
	 * 
	 * @param string $meeting_id
	 * @param string $email
	 * @return mixed
	 */
	public function removeUserFromMeeting( $meeting_id, $email ) {
		$user_id = $this->getUserByEmail( $email, true );

		$result = $this->makeRequest( 'permissions-update', array(
			'principal-id' => $user_id,
			'acl-id' => $meeting_id,
			'permission-id' => 'remove'
				)
		);
		return $result;

	}

	/**
	 * get all users associated with the meeting
	 * 
	 * @param string $meeting_id
	 * @return array
	 */
	public function getMeetingUsers( $meeting_id ) {

		return $this->makeRequest( 'permissions-info', array(
					'acl-id' => $meeting_id
						)
		);

	}

	/**
	 * add a user to a group
	 * 
	 * @param string $user_id
	 * @param string $group_id
	 * @return mixed
	 */
	public function addUserToGroup( $user_id, $group_id ) {

		return $this->makeRequest( 'group-membership-update', array(
					'group-id' => $group_id,
					'principal-id' => $user_id,
					'is-member' => 'true'
				) );

	}

	/**
	 * remove a user from a group
	 * 
	 * @param string $user_id
	 * @param string $group_id
	 * @return mixed
	 */
	public function removeUserFromGroup( $user_id, $group_id ) {
		return $this->makeRequest( 'group-membership-update', array(
					'group-id' => $group_id,
					'principal-id' => $user_id,
					'is-member' => 'false'
				) );

	}

	/**
	 * check an individual user's access level to a meeting
	 * 
	 * @param string $meeting_id
	 * @param string $user_id
	 * 
	 * @return string
	 */
	public function checkUserAccess( $meeting_id, $user_id ) {

		return $this->makeRequest( 'permissions-info', array(
					'acl-id' => $meeting_id,
					'principal-id' => $user_id
						)
		);

	}

	/**
	 * change a user's password, would be used only in rare occasions
	 * 
	 * @param string $user_id
	 * @param string $new_password
	 * @return mixed
	 */
	public function changePassword( $user_id, $new_password ) {

		return $this->makeRequest( 'user-update-pwd', array(
					'user-id' => $user_id,
					'password' => $new_password,
					'password-verify' => $new_password
				) );

	}

	public function __destruct() {

		@curl_close( $this->curl );

	}

	/**
	 * @param string $action
	 * @param array $params
	 * @return mixed
	 * @throws Exception
	 */private function makeRequest( $action, array $params = array( ) ) {
		$url = self::BASE_DOMAIN;
		$url .= 'xml?action=' . $action;
		$url .= '&' . http_build_query( $params );

		curl_setopt( $this->curl, CURLOPT_URL, $url );


		if ( !$this->is_authorized ) {

			$result = curl_exec( $this->curl );
			$info = curl_getinfo( $this->curl );

			$header = substr( $result, 0, $info['header_size'] );
			$body = substr( $result, -$info['download_content_length'] );

			$this->setCookieString( $header );

			$xml = simplexml_load_string( $body );
		} else {

			curl_setopt( $this->curl, CURLOPT_HEADER, false );
			$result = curl_exec( $this->curl );
			$xml = simplexml_load_string( $result );
		}

		$json = json_encode( $xml );
		$data = json_decode( $json, TRUE ); // nice hack!

		if ( !isset( $data['status']['@attributes']['code'] ) || $data['status']['@attributes']['code'] !== 'ok' ) {
			throw new Exception( 'Error performing action: ' . $action . ', ' . $data['status']['@attributes'] );
		}

		return $data;

	}

	/**
	 * parses cookie string from header of cURL response
	 * 
	 * @param string $response
	 * @throws Exception
	 */
	private function setCookieString( $response ) {

		$string = " " . $response;
		$ini = strpos( $string, 'BREEZESESSION=' );
		if ( $ini == 0 ) {
			throw new Exception( 'no session' );
		}
		$ini += strlen( 'BREEZESESSION=' );
		$len = strpos( $string, ';Http', $ini ) - $ini;
		$this->cookie_string = substr( $string, $ini, $len );

	}

}