<?php
	
namespace UKMNorge\Slack\Kjop;

use UKMNorge\Slack\Attachment;
use UKMNorge\Slack\Field;
use UKMNorge\Slack\BuildJSON;
use UKMNorge\Slack\Response as GenericResponse;

class Response extends GenericResponse {
	
	var $navn = null;
	var $til = null;
	var $fra = null;
	var $beskrivelse = null;
	
	public function __construct() {
		parent::__construct( 'in_channel' );
		
		$attachment = new Attachment( 'kjop', 'kjop' );
		$this->addAttachment( $attachment );
	}
	
	public static function labelTil() {
		return 'Leveres til';
	}
	public static function labelFra() {
		return 'Kjøpes fra';
	}
	public static function labelBeskrivelse() {
		return 'Beskrivelse';
	}
	
	private function _preRender() {
		$attachment = $this->getAttachment('kjop');
		
		$attachment->addfield(
			new Field(
				'til',
				self::labelTil(),
				$this->getTil()
			)
		);
		
		$attachment->addField(
			new Field(
				'fra',
				self::labelFra(),
				$this->getFra()
			)
		);
		
		if( !empty( $this->getBeskrivelse() ) ) {
			$attachment->addField(
				new Field(
					'beskrivelse',
					self::labelBeskrivelse(),
					$this->getBeskrivelse(),
					false
				)
			);
		}

		if( $this->hasActions() ) {
			$attachment = $this->getAttachment( 'kjop' );
			
			foreach( $this->getActions() as $action ) {
				$attachment->addAction( $action );
			}
			$this->addAttachment( $attachment );
		}
	}
		
	public function renderToJSON() {
		$this->_preRender();
		
		return BuildJSON::response( $this );
	}
	
	public function setNavn( $navn ) {
		$this->navn = ucfirst( $navn );
		return $this;
	} 
	
	public function getNavn() {
		return $this->navn;
	}
	
	public function setTil( $til ) {
		$this->til = $til;
		return $this;
	}
	public function getTil() {
		return $this->til;
	}

	public function setFra( $fra ) {
		$this->fra = $fra;
		return $this;
	}
	public function getFra() {
		return $this->fra;
	}
	
	public function setBeskrivelse( $beskrivelse ) {
		$this->beskrivelse = $beskrivelse;
		return $this;
	}
	public function getBeskrivelse() {
		return $this->beskrivelse;
	}
}