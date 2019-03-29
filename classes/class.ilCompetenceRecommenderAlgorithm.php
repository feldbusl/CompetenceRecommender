<?php
declare(strict_types=1);

/**
 * Class ilCompetenceRecommenderConfigGUI
 *
 * @author Leonie Feldbusch <feldbusl@informatik.uni-freiburg.de>
 */

class ilCompetenceRecommenderAlgorithm {

	/**
	 * @var \ilCompetenceRecommenderAlgorithm
	 */
	protected static $instance;

	/**
	 * @var \ilDB
	 */
	protected $db;

	/**
	 * @var \ilUser
	 */
	protected $user;

	public function __construct()
	{
		global $DIC, $ilUser;
		$this->db = $DIC->database();
		$this->user = $ilUser;
	}

	protected static function getInstance()
	{
		if (!self::$instance)
		{
			self::$instance = new self;
		}
		return self::$instance;
	}

	public static function getDatabaseObj()
	{
		$instance = self::getInstance();
		return $instance->db;
	}

	public static function getUserObj()
	{
		$instance = self::getInstance();
		return $instance->user;
	}

	public static function hasUserProfile()
	{
		$db = self::getDatabaseObj();
		$user_id = self::getUserObj()->getId();

		// get user profiles
		$result = $db->query("SELECT profile_id FROM skl_profile_user WHERE user_id = '".$user_id."'");
		$profiles = $db->fetchAll($result);

		$profile_settings = new ilSetting("comprec");
		foreach ($profiles as $profile) {
			if ($profile_settings->get("checked_profile_".$profile['profile_id']) == $profile['profile_id']) {
				return true;
			}
		}

		return false;
	}

	public static function getUserProfiles() {
		$db = self::getDatabaseObj();
		$user_id = self::getUserObj()->getId();

		$profilearray = array();

		// get user profiles
		$result = $db->query("SELECT spu.profile_id, sp.title FROM skl_profile_user AS spu JOIN skl_profile AS sp ON sp.id = spu.profile_id WHERE user_id = '".$user_id."'");
		$profiles = $db->fetchAll($result);

		$profile_settings = new ilSetting("comprec");
		foreach ($profiles as $profile) {
			if ($profile_settings->get("checked_profile_".$profile['profile_id']) == $profile['profile_id']) {
				array_push($profilearray, $profile);
			}
		}

		return $profilearray;
	}

	public static function hasUserFinishedAll()
	{
		$db = self::getDatabaseObj();
		$user_id = self::getUserObj()->getId();

		// get user profiles
		$result = $db->query("SELECT profile_id FROM skl_profile_user WHERE user_id = '".$user_id."'");
		$profiles = $db->fetchAll($result);

		$profile_settings = new ilSetting("comprec");
		foreach ($profiles as $profile) {
			if ($profile_settings->get("checked_profile_".$profile['profile_id']) == $profile['profile_id']) {
				$result = $db->query("SELECT spl.level_id, spl.base_skill_id, spl.tref_id
									FROM skl_profile_level AS spl
									WHERE spl.profile_id = '" . $profile["profile_id"] . "'");
				$skills = $db->fetchAll($result);
				foreach ($skills as $skill) {
					$profilegoal = $db->query("SELECT nr FROM skl_level WHERE skill_id = '" . $skill["base_skill_id"] . "' AND id = '" . $skill["level_id"] . "'");
					$goal = $profilegoal->fetchAssoc();
					$score = self::computeScore($skill["tref_id"]);
					if ($score < $goal) {
						return false;
					}
				}
			}
		}

		return true;
	}

	public static function noResourcesLeft()
	{
		$competences = self::getAllCompetencesOfUserProfile();

		foreach ($competences as $competence) {
			foreach ($competence["resources"] as $resource) {
				if ($resource["level"] >= $competence["score"] && $competence["score"] < $competence["goal"]) {
					return false;
				}
			}
		}

		return true;
	}


	public static function getInitObjects($profile_id = -1) {
		$profiles = self::getUserProfiles();
		$settings = new ilSetting("comprec");
		$ref_ids = array();

		foreach ($profiles as $profile) {
			if ($profile_id == -1 || $profile_id == $profile["profile_id"]) {
				$ref_id = $settings->get("init_obj_" . $profile["profile_id"]);
				if (is_numeric($ref_id)) {
					array_push($ref_ids, array("id" => $ref_id, "title" => $profile["title"]));
				}
			}
		}
		return $ref_ids;
	}


	public static function getDataForDesktop(int $n = 3) {
		$allRefIds = array();
		$competences = self::getAllCompetencesOfUserProfile();

		foreach ($competences as $competence) {
			foreach ($competence["resources"] as $resource) {
				if ($resource["level"] >= $competence["score"] && $competence["score"] < $competence["goal"] && $competence["score"] > 0) {
					array_push($allRefIds, $resource);
					break;
				}
			}
		}


		$data = array_slice($allRefIds, 0, $n);

		return $data;
	}

	public static function getAllCompetencesOfUserProfile(int $n = 0, string $sortation = "diff") {
		$db = self::getDatabaseObj();
		$user_id = self::getUserObj()->getId();

		// get user profiles
		$result = $db->query("SELECT profile_id FROM skl_profile_user WHERE user_id = '".$user_id."'");
		$profiles = $db->fetchAll($result);
		$skillsToSort = array();

		foreach ($profiles as $profile) {
			$skillsToSort = self::getCompetencesToProfile($profile, $skillsToSort, $n);
		}

		$sortedSkills = self::sortCompetences($skillsToSort);
		if ($n > 0) {
			return array_slice($sortedSkills, 0, $n);
		}

		return $sortedSkills;
	}

	public static function getCompetencesToProfile($profile, $skillsToSort = array(), int $n = 0) {
		$db = self::getDatabaseObj();

		$profile_settings = new ilSetting("comprec");

		if ($profile_settings->get("checked_profile_".$profile['profile_id']) == $profile['profile_id']) {
			$result = $db->query("SELECT spl.tref_id,spl.base_skill_id,spl.level_id,stn.title
									FROM skl_profile_level AS spl
									JOIN skl_tree_node AS stn ON spl.tref_id = stn.obj_id
									WHERE spl.profile_id = '" . $profile["profile_id"] . "'");
			$skills = $db->fetchAll($result);
			foreach ($skills as $skill) {
				// get data needed for Selfevaluations
				$childId = $skill["tref_id"];
				$depth = 3;
				while ($depth > 2) {
					$parent_query = $db->query("SELECT depth, child, parent
									FROM skl_tree
									WHERE child = '" . $childId . "'");
					$parent = $db->fetchAssoc($parent_query);
					$depth = $parent["depth"];
					$childId = $parent["parent"];
					$parentId = $parent["child"];
				}

				// get resources and score
				$level = $db->query("SELECT * FROM skl_level WHERE skill_id = '" . $skill["base_skill_id"] . "'");
				$levelcount = $level->numRows();
				$profilegoal = $db->query("SELECT nr FROM skl_level WHERE skill_id = '" . $skill["base_skill_id"] . "' AND id = '" . $skill["level_id"] . "'");
				$goal = $profilegoal->fetchAssoc();
				$score = self::computeScore($skill["tref_id"]);
				if ($n == 0 || $score != 0) {
					if (!isset($skillsToSort[$skill["tref_id"]])) {
						$skillsToSort[$skill["tref_id"]] = array(
							"id" => $skill["tref_id"],
							"base_skill" => $skill["base_id"],
							"parent" => $parentId,
							"title" => $skill['title'],
							"lastUsed" => self::getLastUsedDate(intval($skill["tref_id"])),
							"score" => $score,
							"diff" => $score == 0 ? 1 - $goal["nr"] / $levelcount : $score / $goal["nr"],
							"goal" => $goal["nr"],
							"percentage" => $score/$goal["nr"],
							"scale" => $levelcount,
							"resources" => self::getResourcesForCompetence(intval($skill["tref_id"])));
					} else if ($goal["nr"] > $skillsToSort[$skill["tref_id"]]["goal"]) {
						// if several profiles with same skill take maximum
						$skillsToSort[$skill["tref_id"]]["goal"] = $goal["nr"];
						$skillsToSort[$skill["tref_id"]]["percentage"] = $score/$goal["nr"];
					}
				}
			}
		}
		return $skillsToSort;
	}

	public static function sortCompetences(array $competences) {
		$sortation = $_GET["sortation"];
		$valid_sortations = array('diff','percentage','lastUsed','oldest');
		if (in_array($sortation, $valid_sortations)) {
			if ($sortation != 'oldest') {
				$score_sorter = array_column($competences, $sortation);
				if ($sortation != 'lastUsed') {
					array_multisort($score_sorter, SORT_NUMERIC, SORT_ASC, $competences);
				} else {
					array_multisort($score_sorter, SORT_STRING, SORT_ASC, $competences);
				}
			} else {
				$score_sorter = array_column($competences, 'lastUsed');
				array_multisort($score_sorter, SORT_STRING, SORT_DESC, $competences);
			}
		} else {
			$score_sorter = array_column($competences, 'diff');
			array_multisort($score_sorter, SORT_NUMERIC, SORT_ASC, $competences);
		}
		return $competences;
	}

	public static function getNCompetencesOfUserProfile(int $n) {
		$competences = self::getAllCompetencesOfUserProfile($n);
		return $competences;
	}

	private static function computeScore($skill)
	{
		$db = self::getDatabaseObj();
		$user_id = self::getUserObj()->getId();

		$resultLastSelfEval = $db->query("SELECT suhl.level_id, sl.nr, suhl.status_date
								FROM skl_user_has_level AS suhl
								JOIN skl_level AS sl ON suhl.level_id = sl.id
								WHERE suhl.user_id ='" . $user_id . "' 
								AND suhl.tref_id ='" . $skill . "'
								AND suhl.self_eval = '1'
								ORDER BY suhl.status_date DESC");
		$resultLastFremdEval = $db->query("SELECT suhl.level_id, sl.nr, suhl.status_date
								FROM skl_user_has_level AS suhl
								JOIN skl_level AS sl ON suhl.level_id = sl.id
								WHERE suhl.user_id ='" . $user_id . "' 
								AND suhl.tref_id ='" . $skill . "'
								AND suhl.self_eval = '0'
								AND (suhl.trigger_obj_type = 'crs' OR suhl.trigger_obj_type = 'svy')
								ORDER BY suhl.status_date DESC");
		$resultLastMessung = $db->query("SELECT suhl.level_id, sl.nr, suhl.status_date
								FROM skl_user_has_level AS suhl
								JOIN skl_level AS sl ON suhl.level_id = sl.id
								WHERE suhl.user_id ='" . $user_id . "' 
								AND suhl.tref_id ='" . $skill . "'
								AND suhl.self_eval = '0'
								AND suhl.trigger_obj_type != 'crs'
								AND suhl.trigger_obj_type != 'svy'
								ORDER BY suhl.status_date DESC");

		// last value of user levels
		$scoreS = 0;
		$scoreF = 0;
		$scoreM = 0;
		// time in days since value was set
		$t_S = 0;
		$t_F = 0;
		$t_M = 0;
		if ($resultLastSelfEval->numRows() > 0) {
			$valueLastSelfEval = $db->fetchAssoc($resultLastSelfEval);
			$scoreS = intval($valueLastSelfEval["nr"]);
			$t_S = intval(ceil((time() - strtotime($valueLastSelfEval["status_date"])) / 86400));
		}
		if ($resultLastFremdEval->numRows() > 0) {
			$valueLastFremdEval = $db->fetchAssoc($resultLastFremdEval);
			$scoreF = intval($valueLastFremdEval["nr"]);
			$t_F = intval(ceil((time() - strtotime($valueLastFremdEval["status_date"])) / 86400));
		}
		if ($resultLastMessung->numRows() > 0) {
			$valueLastMessung = $db->fetchAssoc($resultLastMessung);
			$scoreM = intval($valueLastMessung["nr"]);
			$t_M = intval(ceil((time() - strtotime($valueLastMessung["status_date"])) / 86400));
		}

		// drop values older than dropout_input
		$dropout_setting = new ilSetting("comprec");
		$dropout_value = $dropout_setting->get("dropout_input");
		if ($dropout_value == null) {$dropout_value = 0;}

		return self::score($t_S, $t_M, $t_F, $scoreS, $scoreM, $scoreF, intval($dropout_value));
	}

	public static function score(int $t_S, int $t_M, int $t_F, int $scoreS, int $scoreM, int $scoreF, int $dropout_value = 0) {
		$score = 0;

		($t_M < $t_S && $t_M != 0) ? $t_minimum = $t_M : $t_minimum = $t_S;
		($t_F > $t_minimum && $t_minimum != 0) ? $t_minimum = $t_minimum : $t_minimum = $t_F;

		if ($dropout_value > 0) {
			$t_S - $t_minimum > $dropout_value ? $t_S = 0 : $t_S = $t_S;
			$t_M - $t_minimum > $dropout_value ? $t_M = 0 : $t_M = $t_M;
			$t_F - $t_minimum > $dropout_value ? $t_F = 0 : $t_F = $t_F;
		}

		// set t_i to value since newest date
		if ($t_S == $t_minimum && $t_minimum > 0) {
			$t_M == 0 ? $t_M = 0 : $t_M -= $t_S - 1;
			$t_F == 0 ? $t_F = 0 : $t_F -= $t_S - 1;
			$t_S = 1;
		} else if ($t_M == $t_minimum && $t_minimum > 0) {
			$t_S == 0 ? $t_S = 0 : $t_S -= $t_M - 1;
			$t_F == 0 ? $t_F = 0 : $t_F -= $t_M - 1;
			$t_M = 1;
		} else if ($t_F == $t_minimum && $t_minimum > 0) {
			$t_M == 0 ? $t_M = 0 : $t_M -= $t_F - 1;
			$t_S == 0 ? $t_S = 0 : $t_S -= $t_F - 1;
			$t_F = 1;
		}

		$m_S = 1/3; $m_F = 1/3; $m_M = 1/3;
		//Fallunterscheidung
		if ($t_S == 0 || $scoreS == 0) {$m_S = 0; $t_S = 0;}
		if ($t_F == 0 || $scoreF == 0) {$m_F = 0; $t_F = 0;}
		if ($t_M == 0 || $scoreM == 0) {$m_M = 0; $t_M = 0;}

		$sum_t = $t_S+$t_F+$t_M;
		//Berechnung
		if ($sum_t != 0) {
			if ($t_S / $sum_t == 1) {
				$score = $scoreS;
			} else if ($t_M / $sum_t == 1) {
				$score = $scoreM;
			} else if ($t_F / $sum_t == 1) {
				$score = $scoreF;
			} else {
				$m_S != 0 ? $sumS = array($sum_t - $t_S, $sum_t* 3) : $sumS=array(0,1);
				$m_M != 0 ? $sumM = array($sum_t - $t_M, $sum_t* 3) : $sumM=array(0,1);
				$m_F != 0 ? $sumF = array($sum_t - $t_F, $sum_t* 3) : $sumF=array(0,1);

				$longdivisor = $sumS[0]*$sumM[1]*$sumF[1]+$sumS[1]*$sumM[0]*$sumF[1]+$sumS[1]*$sumM[1]*$sumF[0];
				$mult = $sumS[1]*$sumM[1]*$sumF[1];
				$scorePartS = bcdiv(strval($sumS[0] *  $mult * $scoreS), strval($sumS[1] * $longdivisor), 10);
				$scorePartM = bcdiv(strval($sumM[0] *  $mult * $scoreM), strval($sumM[1] * $longdivisor), 10);
				$scorePartF = bcdiv(strval($sumF[0] *  $mult * $scoreF), strval($sumF[1] * $longdivisor), 10);
				$score = $scorePartS + $scorePartF + $scorePartM;
				$score = round($score, 3);
			}
		}

		return $score;
	}

	private static function getLastUsedDate(int $skill_id) {
		$db = self::getDatabaseObj();
		$user_id = self::getUserObj()->getId();

		$lastUsedDate = $db->query("SELECT suhl.status_date
								FROM skl_user_has_level AS suhl
								JOIN skl_level AS sl ON suhl.level_id = sl.id
								WHERE suhl.user_id ='" . $user_id . "' 
								AND suhl.tref_id ='" . $skill_id . "'");

		$date = $lastUsedDate->fetchAssoc()["status_date"];
		if (!isset($date)) {
			$date = 9;
		}

		return $date;
	}

	private static function getResourcesForCompetence(int $skill_id) {
		$db = self::getDatabaseObj();

		$refIds = array();
		$result = $db->query("SELECT ssr.rep_ref_id,ssr.tref_id,ssr.level_id,stn.title 
								FROM skl_skill_resource AS ssr 
								JOIN skl_tree_node AS stn ON ssr.tref_id = stn.obj_id
								WHERE ssr.tref_id ='".$skill_id."'");
		$values = $db->fetchAll($result);

		foreach ($values as $value) {
			$level = $db->query("SELECT nr
								FROM skl_level
								WHERE id ='".$value["level_id"]."'");
			$levelnumber = $level->fetchAssoc();
			array_push($refIds, array("id" => $value["rep_ref_id"], "title" => $value["title"], "level" => $levelnumber["nr"]));
		}

		// sort
		$sorter  = array_column($refIds, 'level');
		array_multisort($sorter, SORT_NUMERIC, SORT_ASC, $refIds);

		return $refIds;
	}
}