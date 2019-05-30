<?php


namespace UKMNorge\Slack\Kjop;

use UKMNorge\Slack\Option;
use UKMNorge\Slack\OptionGroup;
use UKMNorge\Slack\SelectAction;
use UKMNorge\Slack\API\Users;

use UKMNorge\Trello\Trello;

use Exception;

class Request
{
	const DEFAULT_LIST_NAME = 'Ukjent butikk';
	static $list_id = null;
	static $from_name = null;

	/**
	 * Parse request data and create Response object
	 *
	 * @return UKMNorge\Slack\Kjop\Response
	 */
	public static function parse()
	{
		/**
		 * MAP VERDIER FRA STRING TIL VARIABLER
		 **/
		#$preg = "/(([0-9]+)([\wæøåÆØÅ]+\b) )?([\d\wæøåÆØÅ ]*) fra ([\d\wæøåÆØÅ]*) til ([\d\wæøåÆØÅ]*)\s?(\<.*\>)?/";
		$preg = "/([0-9\wæøåÆØÅ -_]+) fra ([\d\wæøåÆØÅ -]+) til ([\d\wæøåÆØÅ -]+)(\<(.*)\>)?/";
		$message = [];
		preg_match($preg, $_POST['text'], $message);

		if (sizeof($message) == 0) {
			throw new Exception(
				'Ikke gyldig meldingsformat',
				181001
			);
		}

		$response = new Response();
		$response->setNavn($message[1])
			->setFra($message[2])
			->setTil($message[3]);
		
		if (isset($message[5]) && !empty($message[5])) {
			$response->setBeskrivelse(
				substr(
					$message[5],
					1,
					strlen($message[5]) - 2
				)
			);
		}

		return $response;
	}

	/**
	 * Find item target list from response
	 *
	 * @param UKMNorge\Slack\Kjop\Response $response
	 * @return void
	 */
	public static function findList($response)
	{
		// Store from in request as well as response
		self::setFromName($response->getFra());

		// Fetch list from trello
		$from_list = Trello::getListByName($response->getFra());

		// Update response
		$response->setFra($from_list->name);
		$response->setText($response->getNavn() . ' er lagt til på lista!');

		// Store list id in request
		self::setListId($from_list->id);
	}

	/**
	 * Add target list alternatives to response
	 * Happens when target list is not initially found
	 *
	 * @param [type] $response
	 * @return void
	 */
	public static function showListAlternatives($response)
	{
		$response->setText($response->getNavn() . ' er lagt til på lista, *men vi trenger litt mer info om butikken.*');

		// Select default list
		$from_list = Trello::getListByName(self::DEFAULT_LIST_NAME);
		$response->setFra($from_list->name);
		self::setListId($from_list->id);


		// Create new list Option group
		$optionGroupCreate = new OptionGroup(
			'opprett',
			'Opprett butikken'
		);
		$optionGroupCreate->addOption(
			new Option(
				'new',
				'Opprett `' . self::getFromName() . '`',
				'new-' . urlencode(self::getFromName())
			)
		);

		// EXISTING GROUPS

		// Reference variables - are option groups created?
		$optionGroupLike = false;
		$optionGroupAll = false;

		// Loop all lists in the trello board, looking for similar ones
		foreach (trello::getLists() as $liste) {
			$percent = 0;
			similar_text(strtolower($response->getFra()), strtolower($liste->name), $percent);

			// List name looks alike
			if ($percent > 50) {
				$group = 'optionGroupLike';

				// If this is the first look-a-like, create the Option group
				if (false === $optionGroupLike) {
					$optionGroupLike = new OptionGroup(
						'lignende',
						'Butikker som ligner på `' . self::getFromName() . '`'
					);
				}
			}
			// List name does not look alike
			else {
				$group = 'optionGroupAll';

				// If this is the first look-a-different(🙈), the create Option group
				if (false === $optionGroupAll) {
					$optionGroupAll = new OptionGroup(
						'alle',
						'Alle registrerte butikker'
					);
				}
			}

			// Add the option to the correct Option group
			$$group->addOption(
				new Option(
					$liste->id,
					$liste->name,
					$liste->id
				)
			);
		}

		// CREATE THE SELECT ACTION (with the generated Option groups)

		$fra_action = new SelectAction(
			'fra',
			'Kjøpes fra'
		);

		$fra_action->addOptionGroup($optionGroupCreate);
		if (false !== $optionGroupLike) {
			$fra_action->addOptionGroup($optionGroupLike);
		}
		if (false !== $optionGroupAll) {
			$fra_action->addOptionGroup($optionGroupAll);
		}

		$response->addAction($fra_action);
	}

	/**
	 * Get the user name from request data
	 *
	 * @return String $user name
	 */
	public static function getUserName()
	{
		return self::getUser()->real_name_normalized;
	}

	/**
	 * Get slack user data from request data
	 *
	 * @return stdClass $user_data
	 */
	public static function getUser()
	{
		return Users::profileGet($_POST['user_id']);
	}


	public static function setListId($id)
	{
		self::$list_id = $id;
	}
	public static function getListId()
	{
		return self::$list_id;
	}

	public static function setFromName($name)
	{
		self::$from_name = $name;
	}
	public static function getFromName()
	{
		return self::$from_name;
	}
}
