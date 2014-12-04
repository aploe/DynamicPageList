<?php
/**
 * DynamicPageList
 * DPL Variables Class
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL
 * @package		DynamicPageList
 *
 **/
namespace DPL;

class Parameters extends ParametersData {
	/**
	 * Set parameter options.
	 *
	 * @var		array
	 */
	private $parameterOptions = [];

	/**
	 * Selection Criteria Found
	 *
	 * @var		boolean
	 */
	private $selectionCriteriaFound = false;

	/**
	 * Open References Conflict
	 *
	 * @var		boolean
	 */
	private $openReferencesConflict = false;

	/**
	 * Parameters that have already been processed.
	 *
	 * @var		array
	 */
	private $parametersProcessed = [];

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
		$this->setDefaults();
	}

	/**
	 * Handle simple parameter functions.
	 *
	 * @access	public
	 * @param	string	Function(Parameter) Called
	 * @param	string	Function Arguments
	 * @return	boolean	Successful
	 */
	public function __call($parameter, $arguments) {
		$parameterData = $this->getData($parameter);

		//Check permission to use this parameter.
		if (array_key_exists('permission', $parameterData)) {
			global $wgUser;
			if (!$wgUser->isAllowed($parameterData['permission'])) {
				throw new PermissionsError($parameterData['permission']);
				return;
			}
		}

		//Subvert to the real function if it exists.  This keeps code elsewhere clean from needed to check if it exists first.
		$function = "_".$parameter;
		$this->parametersProcessed[$parameter] = true;
		if (method_exists($this, $function)) {
			return call_user_func_array([$this, $function], $arguments);
		}
		$option = $arguments[0];
		$parameter = strtolower($parameter);

		//Assume by default that these simple parameter options should not failed, but if they do we will set $success to false below.
		$success = true;
		if ($parameterData !== false) {
			//If a parameter specifies options then enforce them.
			if (is_array($parameterData['values']) === true && !in_array(strtolower($option), $parameterData['values'])) {
				$success = false;
			} else {
				if (!$parameterData['preserve_case'] && $parameterData['page_name_list'] !== true) {
					$option = strtolower($option);
				}
			}

			//Strip <html> tag.
			if ($parameterData['strip_html'] === true) {
				$option = $this->stripHtmlTags($option);
			}

			//Simple integer intval().
			if ($parameterData['integer'] === true) {
				if (!is_numeric($option)) {
					if ($parameterData['default'] !== null) {
						$option = intval($parameterData['default']);
					} else {
						$success = false;
					}
				} else {
					$option = intval($option);
				}
			}

			//Booleans
			if ($parameterData['boolean'] === true) {
				$option = $this->filterBoolean($option);
				if ($option === null) {
					$success = false;
				}
			}

			//Timestamps
			if ($parameterData['timestamp'] === true) {
				$option = wfTimestamp(TS_MW, $option);
				if ($option === false) {
					$success = false;
				}
			}

			//List of Pages
			if ($parameterData['page_name_list'] === true) {
				$pageGroups = $this->getParameter($parameter);
				if (!is_array($pageGroups)) {
					$pageGroups = [];
				}
				$pages = $this->getPageNameList($option, (bool) $parameterData['page_name_must_exist']);
				if ($pages === false) {
					$success = false;
				} else {
					$pageGroups[] = $pages;
					$option = $pageGroups;
				}
			}

			//Regex Pattern Matching
			if (array_key_exists('pattern', $parameterData)) {
				if (preg_match($parameterData['pattern'], $option, $matches)) {
					//Nuke the total pattern match off the beginning of the array.
					array_shift($matches);
					$option = $matches;
				} else {
					$success = false;
				}
			}

			//Database Key Formatting
			if ($parameterData['db_format'] === true) {
				$option = str_replace(' ', '_', $option);
			}

			//If none of the above checks marked this as a failure then set it.
			if ($success === true) {
				$this->setParameter($parameter, $option);

				//Set that criteria was found for a selection.
				if ($parameterData['set_criteria_found'] === true) {
					$this->setSelectionCriteriaFound(true);
				}

				//Set open references conflict possibility.
				if ($parameterData['open_ref_conflict'] === true) {
					$this->setOpenReferencesConflict(true);
				}
			}
		}
		return $success;
	}

	/**
	 * Sort cleaned parameter arrays by priority.
	 * Users can not be told to put the parameters into a specific order each time.  Some parameters are dependent on each other coming in a certain order due to some procedural legacy issues.
	 *
	 * @access	public
	 * @param	array	Unsorted Parameters
	 * @return	array	Sorted Parameters
	 */
	public function sortByPriority($parameters) {
		if (!is_array($parameters)) {
			throw new \MWException(__METHOD__.': A non-array was passed.');
		}
		//'category' to get category headings first for ordermethod.
		//'include'/'includepage' to make sure section labels are ready for 'table'.
		$priority = [
			'distinct'			=> 1,
			'openreferences'	=> 2,
			'ignorecase'		=> 3,
			'category'			=> 4,
			'goal'				=> 5,
			'ordercollation'	=> 6,
			'ordermethod'		=> 7,
			'includepage'		=> 8,
			'include'			=> 9
		];
		$_first = array_intersect_key($parameters, $priority);
		if (count($_first)) {
			foreach ($_first as $key => $value) {
				unset($parameters[$key]);
			}
			$parameters = array_merge($_first, $parameters);
		}
		return $parameters;
	}

	/**
	 * Set Selection Criteria Found
	 *
	 * @access	public
	 * @param	boolean	Is Found?
	 * @return	void
	 */
	private function setSelectionCriteriaFound($found = true) {
		if (!is_bool($found)) {
			throw new MWException(__METHOD__.': A non-boolean was passed.');
		}
		$this->selectionCriteriaFound = $found;
	}

	/**
	 * Get Selection Criteria Found
	 *
	 * @access	public
	 * @return	boolean	Is Conflict?
	 */
	public function isSelectionCriteriaFound() {
		return $this->selectionCriteriaFound;
	}

	/**
	 * Set Open References Conflict - See 'openreferences' parameter.
	 *
	 * @access	public
	 * @param	boolean	References Conflict?
	 * @return	void
	 */
	private function setOpenReferencesConflict($conflict = true) {
		if (!is_bool($conflict)) {
			throw new MWException(__METHOD__.': A non-boolean was passed.');
		}
		$this->openReferencesConflict = $conflict;
	}

	/**
	 * Get Open References Conflict - See 'openreferences' parameter.
	 *
	 * @access	public
	 * @return	boolean	Is Conflict?
	 */
	public function isOpenReferencesConflict() {
		return $this->openReferencesConflict;
	}

	/**
	 * Set default parameters based on ParametersData.
	 *
	 * @access	private
	 * @return	void
	 */
	private function setDefaults() {
		$this->setParameter('defaulttemplatesuffix', '.default');

		$parameters = $this->getParametersForRichness();
		foreach ($parameters as $parameter) {
			if ($this->getData($parameter)['default'] !== null && !($this->getData($parameter)['default'] === false && $this->getData($parameter)['boolean'] === true)) {
				if ($parameter == 'debug') {
					\DynamicPageListHooks::setDebugLevel($this->getData($parameter)['default']);
				}
				$this->setParameter($parameter, $this->getData($parameter)['default']);
			}
		}
	}

	/**
	 * Set a parameter's option.
	 *
	 * @access	public
	 * @param	string	Parameter to set
	 * @param	mixed	Option to set
	 * @return	void
	 */
	public function setParameter($parameter, $option) {
		$this->parameterOptions[$parameter] = $option;
	}

	/**
	 * Get a parameter's option.
	 *
	 * @access	public
	 * @param	string	Parameter to get
	 * @return	mixed	Option for specified parameter.
	 */
	public function getParameter($parameter) {
		return $this->parameterOptions[$parameter];
	}

	/**
	 * Get all parameters.
	 *
	 * @access	public
	 * @return	array	Parameter => Options
	 */
	public function getAllParameters() {
		return $this->parameterOptions;
	}

	/**
	 * Filter a standard boolean like value into an actual boolean.
	 *
	 * @access	public
	 * @param	mixed	Integer or string to evaluated through filter_var().
	 * @return	boolean
	 */
	public function filterBoolean($boolean) {
		return filter_var($boolean, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
	}

	/**
	 * Strip <html> tags.
	 *
	 * @access	private
	 * @param	string	Dirty Text
	 * @return	string	Clean Text
	 */
	private function stripHtmlTags($text) {
		$text = preg_replace("#<.*?html.*?>#is", "", $text);

		return $text;
	}

	/**
	 * Get a list of valid page names.
	 *
	 * @access	private
	 * @param	string	Raw Text of Pages
	 * @param	boolean	[Optional] Each Title MUST Exist
	 * @return	mixed	List of page titles or false on error.
	 */
	private function getPageNameList($text, $mustExist = true) {
		$list = [];
		$pages = explode('|', trim($text));
		foreach ($pages as $page) {
			$page = trim($page);
			$page = rtrim($page, '\\'); //This was fixed from the original code, but I am not sure what its intended purpose was.
			if (empty($page)) {
				continue;
			}
			if ($mustExist === true) {
				$title = \Title::newFromText($page);
				if (!$title) {
					return false;
				}
				$list[] = $title;
			} else {
				$list[] = $page;
			}
		}

		return $list;
	}

	/**
	 * Clean and test 'category' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _category($option) {
		$option = trim($option);
		if (empty($option)) {
			return false;
		}
		// Init array of categories to include
		$categories = [];
		$heading    = false;
		$notHeading = false;
		if (substr($option, 0, 1) == '+') { // categories are headings
			$heading = true;
			$option = ltrim($option, '+');
		}
		if (substr($option, 0, 1) == '-') { // categories are NOT headings
			$notHeading = true;
			$option = ltrim($option, '-');
		}

		$operator = 'OR';
		//We expand html entities because they contain an '& 'which would be interpreted as an AND condition
		$option = html_entity_decode($option, ENT_QUOTES);
		if (strpos($option, '&') !== false) {
			$parameters = explode('&', $option);
			$operator = 'AND';
		} else {
			$parameters = explode('|', $option);
		}
		foreach ($parameters as $parameter) {
			$parameter = trim($parameter);
			if ($parameter == '_none_' || $parameter === '') {
				$parameters[$parameter] = '';
				$this->getParameter('includeuncat', true);
				$categories[]    = '';
			} elseif (!empty($parameter)) {
				if (substr($parameter, 0, 1) == '*' && strlen($parameter) >= 2) {
					if (substr($parameter, 1, 2) == '*') {
						$subCategories = explode('|', self::getSubcategories(substr($parameter, 2), 2));
					} else {
						$subCategories = explode('|', self::getSubcategories(substr($parameter, 1), 1));
					}
					foreach ($subCategories as $subCategory) {
						$title = \Title::newFromText($subCategory);
						if (!is_null($title)) {
							$categories[] = $title->getDbKey();
						}
					}
				} else {
					$title = \Title::newFromText($parameter);
					if (!is_null($title)) {
						$categories[] = $title->getDbKey();
					}
				}
			}
		}
		if (!empty($categories)) {
			$data = $this->getParameter('category');
			if (!is_array($data[$operator])) {
				$data[$operator] = [];
			}
			$data[$operator] = array_merge($data[$operator], $categories);
			$this->setParameter('category', $data);
			if ($heading) {
				$this->setParameter('catheadings', array_unique(array_merge($this->getParameter('catheadings'), $categories)));
			}
			if ($notHeading) {
				$this->setParameter('catnotheadings', array_unique(array_merge($this->getParameter('catnotheadings'), $categories)));
			}
			$this->setOpenReferencesConflict(true);
			return true;
		}
		return false;
	}

	/**
	 * Clean and test 'categoryregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _categoryregexp($option) {
		$data = $this->getParameter('category');
		$data['regexp'][] = $option;
		$this->setParameter('category', $data);
		$this->setOpenReferencesConflict(true);
		return true;
	}

	/**
	 * Clean and test 'categorymatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _categorymatch($option) {
		$data = $this->getParameter('category');
		if (!is_array($data['like'])) {
			$data['like'] = [];
		}
		$newMatches = explode('|', $option);
		$data['like'] = array_merge($data['like'], $newMatches);
		$this->setParameter('category', $data);
		$this->setOpenReferencesConflict(true);
		return true;
	}

	/**
	 * Clean and test 'notcategory' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _notcategory($option) {
		$title = \Title::newFromText($option);
		if (!is_null($title)) {
			$data = $this->getParameter('notcategory');
			$data['='][] = $title->getDbKey();
			$this->setParameter('notcategory', $data);
			$this->setOpenReferencesConflict(true);
			return true;
		}
		return false;
	}

	/**
	 * Clean and test 'notcategoryregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _notcategoryregexp($option) {
		$data = $this->getParameter('notcategory');
		$data['regexp'][] = $option;
		$this->setParameter('notcategory', $data);
		$this->setOpenReferencesConflict(true);
		return true;
	}

	/**
	 * Clean and test 'notcategorymatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _notcategorymatch($option) {
		$data = $this->getParameter('notcategory');
		if (!is_array($data['like'])) {
			$data['like'] = [];
		}
		$newMatches = explode('|', $option);
		$data['like'] = array_merge($data['like'], $newMatches);
		$this->setParameter('notcategory', $data);
		$this->setOpenReferencesConflict(true);
		return true;
	}

	/**
	 * Clean and test 'count' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _count($option) {
		if (!Config::getSetting('allowUnlimitedResults') && $option <= Config::getSetting('maxResultCount') && $option > 0) {
			$this->setParameter('count', intval($option));
			return true;
		}
		return false;
	}

	/**
	 * Clean and test 'namespace' parameter.
	 *
	 * @access	public
	 * @param	string	Option passed to parameter.
	 * @return	boolean	Success
	 */
	public function _namespace($option) {
		global $wgContLang;
		$extraParams = explode('|', $option);
		foreach ($extraParams as $parameter) {
			$parameter = trim($parameter);
			$namespaceId = $wgContLang->getNsIndex($parameter);
			if ($namespaceId === false || (is_array(Config::getSetting('allowedNamespaces')) && !in_array($parameter, Config::getSetting('allowedNamespaces')))) {
				//Let the user know this namespace is not allowed or does not exist.
				return false;
			}
			$data = $this->getParameter('namespace');
			$data[] = $namespaceId;
			$data = array_unique($data);
			$this->setParameter('namespace', $data);
			$this->setSelectionCriteriaFound(true);
		}
		return true;
	}

	/**
	 * Clean and test 'notnamespace' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _notnamespace($option) {
		global $wgContLang;
		$extraParams = explode('|', $option);
		foreach ($extraParams as $parameter) {
			$parameter = trim($parameter);
			$namespaceId = $wgContLang->getNsIndex($parameter);
			if ($namespaceId === false) {
				//Let the user know this namespace is not allowed or does not exist.
				return false;
			}
			$data = $this->getParameter('notnamespace');
			$data[] = $namespaceId;
			$data = array_unique($data);
			$this->setParameter('notnamespace', $data);
			$this->setSelectionCriteriaFound(true);
		}
		return true;
	}

	/**
	 * Clean and test 'ordermethod' parameter.(NOTE THE PLURAL 'S')
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _ordermethod($option) {
		$methods = explode(',', $option);
		$success = true;
		foreach ($methods as $method) {
			if (!in_array($method, $this->getData('ordermethod')['values'])) {
				return false;
			}
		}

		$this->setParameter('ordermethod', $methods);
		if ($methods[0] !== 'none') {
			$this->setOpenReferencesConflict(true);
		}

		return true;
	}

	/**
	 * Clean and test 'mode' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _mode($option) {
		if (in_array($option, $this->getData('mode')['values'])) {
			//'none' mode is implemented as a specific submode of 'inline' with <br/> as inline text
			if ($option == 'none') {
				$this->setParameter('mode', 'inline');
				$this->setParameter('inltxt', '<br/>');
			} elseif ($option == 'userformat') {
				// userformat resets inline text to empty string
				$this->setParameter('inltxt', '');
				$this->setParameter('mode', $option);
			} else {
				$this->setParameter('mode', $option);
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'distinct' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _distinct($option) {
		$boolean = $this->filterBoolean($option);
		if ($option == 'strict') {
			$this->setParameter('distinctresultset', 'strict');
		} elseif ($boolean !== null) {
			$this->setParameter('distinctresultset', $boolean);
		} else {
			return false;
		}
		return true;
	}

	/**
	 * Clean and test 'ordercollation' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _ordercollation($option) {
		if ($option == 'bridge') {
			$this->setParameter('ordersuitsymbols', true);
		} elseif (!empty($option)) {
			$this->setParameter('ordercollation', $option);
		} else {
			return false;
		}
		return true;
	}

	/**
	 * Short cut to _format();
	 *
	 * @access	public
	 * @return	mixed
	 */
	public function _listseparators() {
		return call_user_func_array([$this, '_format'], func_get_args());
	}

	/**
	 * Clean and test 'format' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _format($option) {
		//Parsing of wikitext will happen at the end of the output phase.  Replace '\n' in the input by linefeed because wiki syntax depends on linefeeds.
		$option = $this->stripHtmlTags($option);
		$option = Parse::replaceNewLines($option);
		$this->setParameter('listseparators', explode(',', $option, 4));
		//Set the 'mode' parameter to userformat automatically.
		$this->setParameter('mode', 'userformat');
		$this->setParameter('inltxt', '');
		return true;
	}

	/**
	 * Clean and test 'title' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _title($option) {
		$title = \Title::newFromText($option);
		if ($title) {
			$data = $this->getParameter('title');
			$data['='][] = str_replace(' ', '_', $title->getText());
			$this->setParameter('title', $data);
			$this->setParameter('namespaces', array_merge((is_array($this->getParameter('namespaces')) ? $this->getParameter('namespaces') : []), $title->getNamespace()));
			$this->setParameter('mode', 'userformat');
			$this->setParameter('ordermethod', []);
			$this->setSelectionCriteriaFound(true);
			$this->setOpenReferencesConflict(true);
			return true;
		}
		return false;
	}

	/**
	 * Clean and test 'titleregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _titleregexp($option) {
		$data = $this->getParameter('title');
		if (!is_array($data['regexp'])) {
			$data['regexp'] = [];
		}
		$newMatches = explode('|', str_replace(' ', '\_', $option));
		$data['regexp'] = array_merge($data['regexp'], $newMatches);
		$this->setParameter('title', $data);
		$this->setSelectionCriteriaFound(true);
		return true;
	}

	/**
	 * Clean and test 'titlematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _titlematch($option) {
		$data = $this->getParameter('title');
		if (!is_array($data['like'])) {
			$data['like'] = [];
		}
		$newMatches = explode('|', str_replace(' ', '\_', $option));
		$data['like'] = array_merge($data['like'], $newMatches);
		$this->setParameter('title', $data);
		$this->setSelectionCriteriaFound(true);
		return true;
	}

	/**
	 * Clean and test 'nottitleregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _nottitleregexp($option) {
		$data = $this->getParameter('nottitle');
		if (!is_array($data['regexp'])) {
			$data['regexp'] = [];
		}
		$newMatches = explode('|', str_replace(' ', '\_', $option));
		$data['regexp'] = array_merge($data['regexp'], $newMatches);
		$this->setParameter('nottitle', $data);
		$this->setSelectionCriteriaFound(true);
		return true;
	}

	/**
	 * Clean and test 'nottitlematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _nottitlematch($option) {
		$data = $this->getParameter('nottitle');
		if (!is_array($data['like'])) {
			$data['like'] = [];
		}
		$newMatches = explode('|', str_replace(' ', '\_', $option));
		$data['like'] = array_merge($data['like'], $newMatches);
		$this->setParameter('nottitle', $data);
		$this->setSelectionCriteriaFound(true);
		return true;
	}

	/**
	 * Clean and test 'scroll' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _scroll($option) {
		$option = $this->filterBoolean($option);
		$this->setParameter('scroll', $option);
		//If scrolling is active we adjust the values for certain other parameters based on URL arguments
		if ($option === true) {
			global $wgRequest;

			//The 'findTitle' option has argument over the 'fromTitle' argument.
			$titlegt = $wgRequest->getVal('DPL_findTitle', '');
			if (!empty($titlegt)) {
				$titlegt = '=_'.ucfirst($titlegt);
			} else {
				$titlegt = $wgRequest->getVal('DPL_fromTitle', '');
				$titlegt = ucfirst($titlegt);
			}
			$this->setParameter('titlegt', str_replace(' ', '_', $titlegt));

			//Lets get the 'toTitle' argument.
			$titlelt = $wgRequest->getVal('DPL_toTitle', '');
			$titlelt = ucfirst($titlelt);
			$this->setParameter('titlelt', str_replace(' ', '_', $titlelt));

			//Make sure the 'scrollDir' arugment is captured.  This is mainly used for the Variables extension and in the header/footer replacements.
			$this->setParameter('scrolldir', $wgRequest->getVal('DPL_scrollDir', ''));

			//Also set count limit from URL if not otherwise set.
			$this->setParameter('scroll', $wgRequest->getVal('DPL_count', ''));
		}
		//We do not return false since they could have just left it out.  Who knows why they put the parameter in the list in the first place.
		return true;
	}

	/**
	 * Clean and test 'replaceintitle' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _replaceintitle($option) {
		//We offer a possibility to replace some part of the title
		$replaceInTitle = explode(',', $option, 2);
		if (isset($replaceInTitle[1])) {
			$replaceInTitle[1] = $this->stripHtmlTags($replaceInTitle[1]);
		}

		$this->setParameter('replaceintitle', $replaceInTitle);

		return true;
	}

	/**
	 * Clean and test 'debug' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _debug($option) {
		if (in_array($option, $this->getData('debug')['values'])) {
			\DynamicPageListHooks::setDebugLevel($option);
		} else {
			return false;
		}

		return true;
	}

	/**
	 * Short cut to _include();
	 *
	 * @access	public
	 * @return	mixed
	 */
	public function _includepage() {
		return call_user_func_array([$this, '_include'], func_get_args());
	}

	/**
	 * Clean and test 'include' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _include($option) {
		if (!empty($option)) {
			$this->setParameter('incpage', true);
			$this->setParameter('seclabels', explode(',', $option));
		} else {
			return false;
		}
		return true;
	}

	/**
	 * Clean and test 'includematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _includematch($option) {
		$this->setParameter('seclabelsmatch', explode(',', $option));
		return true;
	}

	/**
	 * Clean and test 'includematchparsed' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _includematchparsed($option) {
		$this->setParameter('incparsed', true);
		$this->setParameter('seclabelsmatch', explode(',', $option));
		return true;
	}

	/**
	 * Clean and test 'includenotmatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _includenotmatch($option) {
		$this->setParameter('seclabelsnotmatch', explode(',', $option));
		return true;
	}

	/**
	 * Clean and test 'includenotmatchparsed' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _includenotmatchparsed($option) {
		$this->setParameter('incparsed', true);
		$this->setParameter('seclabelsnotmatch', explode(',', $option));
		return true;
	}

	/**
	 * Clean and test 'secseparators' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _secseparators($option) {
		//We replace '\n' by newline to support wiki syntax within the section separators
		$this->setParameter('secseparators', explode(',', Parse::replaceNewLines($option)));
		return true;
	}

	/**
	 * Clean and test 'multisecseparators' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _multisecseparators($option) {
		//We replace '\n' by newline to support wiki syntax within the section separators
		$this->setParameter('multisecseparators', explode(',', Parse::replaceNewLines($option)));
		return true;
	}

	/**
	 * Clean and test 'table' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _table($option) {
		$this->setParameter('defaulttemplatesuffix', '');
		$this->setParameter('mode', 'userformat');
		$this->setParameter('inltxt', '');
		$withHLink             = "[[%PAGE%|%TITLE%]]\n|";

		foreach (explode(',', $option) as $tabnr => $tab) {
			if ($tabnr == 0) {
				if ($tab == '') {
					$tab = 'class=wikitable';
				}
				$listSeparators[0] = '{|' . $tab;
			} else {
				if ($tabnr == 1 && $tab == '-') {
					$withHLink = '';
					continue;
				}
				if ($tabnr == 1 && $tab == '') {
					$tab = wfMessage('article')->text();
				}
				$listSeparators[0] .= "\n!{$tab}";
			}
		}
		$listSeparators[1] = '';
		// the user may have specified the third parameter of 'format' to add meta attributes of articles to the table
		if (!array_key_exists(2, $listSeparators)) {
			$listSeparators[2] = '';
		}
		$listSeparators[3] = "\n|}";
		//Overwrite 'listseparators'.
		$this->setParameter('listseparators', $listSeparators);

		$sectionLabels = $this->getParameter('seclabels');
		$sectionSeparators = $this->getParameter('secseparators');
		$multiSectionSeparators = $this->getParameter('multisecseparators');
		for ($i = 0; $i < count($sectionLabels); $i++) {
			if ($i == 0) {
				$sectionSeparators[0]		= "\n|-\n|" . $withHLink; //."\n";
				$sectionSeparators[1]		= '';
				$multiSectionSeparators[0]	= "\n|-\n|" . $withHLink; // ."\n";
			} else {
				$sectionSeparators[2 * $i]		= "\n|"; // ."\n";
				$sectionSeparators[2 * $i + 1]	= '';
				if (is_array($sectionLabels[$i]) && $sectionLabels[$i][0] == '#') {
					$multiSectionSeparators[$i] = "\n----\n";
				}
				if ($sectionLabels[$i][0] == '#') {
					$multiSectionSeparators[$i] = "\n----\n";
				} else {
					$multiSectionSeparators[$i] = "<br/>\n";
				}
			}
		}
		//Overwrite 'secseparators' and 'multisecseparators'.
		$this->setParameter('secseparators', $sectionSeparators);
		$this->setParameter('multisecseparators', $multiSectionSeparators);

		$this->setParameter('table', Parse::replaceNewLines($option));
		return true;
	}

	/**
	 * Clean and test 'tablerow' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _tablerow($option) {
		$option = Parse::replaceNewLines(trim($option));
		if (empty($option)) {
			$this->setParameter('tablerow', []);
		} else {
			$this->setParameter('tablerow', explode(',', $option));
		}
		return true;
	}

	/**
	 * Clean and test 'allowcachedresults' parameter.
	 * This function is necessary for the custom 'yes+warn' option that sets 'warncachedresults'.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _allowcachedresults($option) {
		//If execAndExit was previously set (i.e. if it is not empty) we will ignore all cache settings which are placed AFTER the execandexit statement thus we make sure that the cache will only become invalid if the query is really executed.
		if ($this->getParameter('execandexit') === null) {
			if ($option == 'yes+warn') {
				$this->setParameter('allowcachedresults', true);
				$this->setParameter('warncachedresults', true);
				return true;
			}
			$option = $this->filterBoolean($option);
			if ($option !== null) {
				$this->setParameter('allowcachedresults', $this->filterBoolean($option));
			} else {
				return false;
			}
		} else {
			$this->setParameter('allowcachedresults', false);
		}
		return true;
	}

	/**
	 * Clean and test 'fixcategory' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _fixcategory($option) {
		\DynamicPageListHooks::fixCategory($option);
		return true;
	}

	/**
	 * Clean and test 'reset' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _reset($option) {
		$arguments = explode(',', $option);
		$reset = [];
		foreach ($arguments as $argument) {
			$argument = trim($argument);
			if (empty($argument)) {
				continue;
			}

			$values = $this->getData('reset')['values'];
			if (!in_array($argument, $value)) {
				return false;
			} else {
				if ($argument == 'all' || $argument == 'none') {
					$boolean = ($argument == 'all' ? true : false);
					$values = array_diff($values, ['all', 'none']);
					$reset = array_flip($values);
					foreach ($reset as $value => $key) {
						$reset[$value] = $boolean;
					}
				} else {
					$reset[$argument] = true;
				}
			}
		}
		$data = $this->getParameter('reset');
		$data = array_merge($data, $reset);
		$this->setParameter('reset', $data);
		return true;
	}

	/**
	 * Clean and test 'eliminate' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _eliminate($option) {
		$arguments = explode(',', $option);
		$eliminate = [];
		foreach ($arguments as $argument) {
			$argument = trim($argument);
			if (empty($argument)) {
				continue;
			}

			$values = $this->getData('eliminate')['values'];
			if (!in_array($argument, $value)) {
				return false;
			} else {
				if ($argument == 'all' || $argument == 'none') {
					$boolean = ($argument == 'all' ? true : false);
					$values = array_diff($values, ['all', 'none']);
					$eliminate = array_flip($values);
					foreach ($reset as $value => $key) {
						$eliminate[$value] = $boolean;
					}
				} else {
					$eliminate[$argument] = true;
				}
			}
		}
		$data = $this->getParameter('eliminate');
		$data = array_merge($data, $eliminate);
		$this->setParameter('eliminate', $data);
		return true;
	}
}
?>