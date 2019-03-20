<?php
declare(strict_types=1);

include_once("./Services/PersonalDesktop/classes/class.ilPersonalDesktopGUI.php");

include_once("class.ilCompetenceRecommenderActivitiesGUI.php");
include_once("class.ilCompetenceRecommenderAllGUI.php");
include_once("class.ilCompetenceRecommenderInfoGUI.php");

/**
 * Class ilCompetenceRecommenderGUI
 *
 * Generated by srag\PluginGenerator v0.9.7
 *
 * @author Leonie Feldbusch <feldbusl@informatik.uni-freiburg.de>
 *
 * @ilCtrl_isCalledBy ilCompetenceRecommenderGUI: ilCompetenceRecommenderUIHookGUI, ilUIPluginRouterGUI
 * @ilCtrl_Calls ilCompetenceRecommenderGUI: ilCompetenceRecommenderActivitiesGUI, ilCompetenceRecommenderAllGUI, ilCompetenceRecommenderInfoGUI
 */
class ilCompetenceRecommenderGUI {

	const PLUGIN_CLASS_NAME = ilCompetenceRecommenderPlugin::class;
	const CMD_COMPREC_STD = "dashboard";

    /** @var  ilCtrl */
    protected $ctrl;

    /** @var  ilTabsGUI */
    protected $tabs;

    /** @var  ilTemplate */
    public $tpl;

	/** @var  ilCompetenceRecommenderPlugin */
    public $pl;

	/** @var  ilUIFramework */
	public $ui;

	/** @var  ilDB */
	public $db;

	/**
	 * CompetenceRecommenderGUI constructor
	 */
	public function __construct() {
		global $ilCtrl, $ilTabs, $tpl, $DIC;
        $this->ctrl = $ilCtrl;
        $this->tabs = $ilTabs;
        $this->tpl = $tpl;
		$this->ui = $DIC->ui();
		$this->db = $DIC->database();
		$this->pl = ilCompetenceRecommenderPlugin::getInstance();
	}


	/**
	 *
	 */
	public function executeCommand()/*: void*/ {
		if (!$this->pl->isActive()) {
			ilUtil::sendFailure('Activate Plugin first', true);
			ilUtil::redirect('index.php');
		}
		$cmd = ($this->ctrl->getCmd()) ? $this->ctrl->getCmd() : $this->getStandardCommand();
		$this->setTabs();
		$next_class = $this->ctrl->getNextClass();

		switch ($next_class) {
			case 'ilcompetencerecommenderactivitiesgui':
				$this->forwardShow();
				break;
			case 'ilcompetencerecommenderallgui':
				$this->forwardAll();
				break;
			case 'ilcompetencerecommenderinfogui':
				$this->forwardInfo();
				break;
			default:
				switch ($cmd) {
					case self::CMD_COMPREC_STD:
					case 'show':
						$this->forwardShow();
						break;
					case 'info':
						$this->forwardInfo();
						break;
					case 'eval':
						$this->forwardAll();
						break;
					default:
						throw new Exception("ilCompetenceRecommenderGUI: Unknown command: ".$cmd);
						break;
				}
		}

		return true;
	}


	/**
	 * Get standard command.
	 *
	 * @return 	string
	 */
	public function getStandardCommand()
	{
		return self::CMD_COMPREC_STD;
	}

	/**
	 *
	 */
	protected function forwardShow()
	{
		$this->tabs->activateTab("show");
		$gui = new \ilCompetenceRecommenderActivitiesGUI();
		$this->ctrl->forwardCommand($gui);
	}

	/**
	 *
	 */
	protected function forwardAll()
	{
		$this->tabs->activateTab("all");
		$gui = new \ilCompetenceRecommenderAllGUI();
		$this->ctrl->forwardCommand($gui);
	}

	/**
	 *
	 */
	protected function forwardInfo()
	{
		$this->tabs->activateTab("info");
		$gui = new \ilCompetenceRecommenderInfoGUI();
		$this->ctrl->forwardCommand($gui);
	}

	/**
	 * Set the tabs for the site.
	 *
	 * @return 	void
	 */
	protected function setTabs()
	{
		// Tabs
		$this->tabs->setBack2Target("Back to PD", $this->ctrl->getLinkTargetByClass("ilPersonalDesktopGUI"));
		$this->tabs->addTab('show', "Aktivitäten", $this->ctrl->getLinkTargetByClass(ilCompetenceRecommenderActivitiesGUI::class));
		$this->tabs->addTab('all', "Alle Empfehlungen", $this->ctrl->getLinkTargetByClass(ilCompetenceRecommenderAllGUI::class));
		$this->tabs->addTab('info', "Info", $this->ctrl->getLinkTargetByClass(ilCompetenceRecommenderInfoGUI::class));
		$this->tabs->activateTab('show');
	}
}
