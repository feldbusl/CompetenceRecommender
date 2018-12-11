<?php

namespace srag\DIC\CompetenceRecommender;

use srag\DIC\CompetenceRecommender\DIC\DICInterface;
use srag\DIC\CompetenceRecommender\Exception\DICException;
use srag\DIC\CompetenceRecommender\Output\OutputInterface;
use srag\DIC\CompetenceRecommender\Plugin\PluginInterface;
use srag\DIC\CompetenceRecommender\Version\VersionInterface;

/**
 * Interface DICStaticInterface
 *
 * @package srag\DIC\CompetenceRecommender
 *
 * @author  studer + raimann ag - Team Custom 1 <support-custom1@studer-raimann.ch>
 */
interface DICStaticInterface {

	/**
	 * Get DIC interface
	 *
	 * @return DICInterface DIC interface
	 */
	public static function dic()/*: DICInterface*/
	;


	/**
	 * Get output interface
	 *
	 * @return OutputInterface Output interface
	 */
	public static function output()/*: OutputInterface*/
	;


	/**
	 * Get plugin interface
	 *
	 * @param string $plugin_class_name
	 *
	 * @return PluginInterface Plugin interface
	 *
	 * @throws DICException Class $plugin_class_name not exists!
	 * @throws DICException Class $plugin_class_name not extends ilPlugin!
	 * @logs   DEBUG Please implement $plugin_class_name::getInstance()!
	 */
	public static function plugin(/*string*/
		$plugin_class_name)/*: PluginInterface*/
	;


	/**
	 * Get version interface
	 *
	 * @return VersionInterface Version interface
	 */
	public static function version()/*: VersionInterface*/
	;
}
