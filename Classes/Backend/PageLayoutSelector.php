<?php
namespace FluidTYPO3\Fluidpages\Backend;

/*
 * This file is part of the FluidTYPO3/Fluidpages project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Fluidpages\Service\ConfigurationService;
use FluidTYPO3\Fluidpages\Service\PageService;
use FluidTYPO3\Flux\Form;
use FluidTYPO3\Flux\Utility\ExtensionNamingUtility;
use FluidTYPO3\Flux\Utility\MiscellaneousUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class that renders a Page template selection field.
 */
class PageLayoutSelector {

	/**
	 * @var BackendConfigurationManager
	 */
	protected $configurationManager;

	/**
	 * @var ConfigurationService
	 */
	protected $configurationService;

	/**
	 * @var array
	 */
	protected $recognizedFormats = array('html', 'xml', 'txt', 'json', 'js', 'css');

	/**
	 * @var PageService
	 */
	protected $pageService;

	/**
	 * CONSTRUCTOR
	 */
	public function __construct() {
		$objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
		$this->configurationManager = $objectManager->get('TYPO3\\CMS\\Extbase\\Configuration\\BackendConfigurationManager');
		$this->configurationService = $objectManager->get('FluidTYPO3\\Fluidpages\\Service\\ConfigurationService');
		$this->pageService = $objectManager->get('FluidTYPO3\\Fluidpages\\Service\\PageService');
	}

	/**
	 * Renders a Fluid Page Layout file selector
	 *
	 * @param array $parameters
	 * @param mixed $pObj
	 * @return string
	 */
	public function renderField(&$parameters, &$pObj) {
		$name = $parameters['itemFormElName'];
		$value = $parameters['itemFormElValue'];
		$availableTemplates = $this->pageService->getAvailablePageTemplateFiles();
		if (FALSE === strpos($name, 'tx_fed_controller_action_sub')) {
			$onChange = 'onclick="if (confirm(TBE_EDITOR.labels.onChangeAlert) && TBE_EDITOR.checkSubmit(-1)){ TBE_EDITOR.submitForm() };"';
		}
		$selector = '<div>';
		$typoScript = $this->configurationManager->getTypoScriptSetup();
		$hideInheritFieldSiteRoot = (boolean) (TRUE === isset($typoScript['plugin.']['tx_fluidpages.']['siteRootInheritance']) ? 1 > $typoScript['plugin.']['tx_fluidpages.']['siteRootInheritance'] : FALSE);
		$pageIsSiteRoot = (boolean) ($parameters['row']['is_siteroot']);
		$forceDisplayInheritSiteRoot = (boolean) ('tx_fed_page_controller_action_sub' === $parameters['field']);
		$forceHideInherit = (boolean) (0 === intval($parameters['row']['pid']));
		if (FALSE === $pageIsSiteRoot || TRUE === $forceDisplayInheritSiteRoot || FALSE === $hideInheritFieldSiteRoot) {
			if (FALSE === $forceHideInherit) {
				$emptyLabel = LocalizationUtility::translate('pages.tx_fed_page_controller_action.default', 'Fluidpages');
				$selected = TRUE === empty($value) ? ' checked="checked" ' : NULL;
				$selector .= '<label>';
				$selector .= '<input type="radio" name="' . $name . '" ' . $onChange . '" value="" ' . $selected . '/> ' . $emptyLabel . LF;
				$selector .= '</label>' . LF;
			}
		}
		foreach ($availableTemplates as $extension=>$group) {
			$extensionKey = ExtensionNamingUtility::getExtensionKey($extension);
			if (FALSE === ExtensionManagementUtility::isLoaded($extensionKey)) {
				$groupTitle = ucfirst($extension);
			} else {
				$emConfigFile = ExtensionManagementUtility::extPath($extensionKey, 'ext_emconf.php');
				require $emConfigFile;
				$groupTitle = $EM_CONF['']['title'];
			}

			$packageLabel = LocalizationUtility::translate('pages.tx_fed_page_package', 'Fluidpages');
			$selector .= '<h4 style="clear: both; margin-top: 1em;">' . $packageLabel . ': ' . $groupTitle . '</h4>' . LF;
			foreach ($group as $template) {
				try {
					$paths = $this->configurationService->getPageConfiguration($extension);
					$extensionName = ExtensionNamingUtility::getExtensionName($extension);
					$templatePathAndFilename = $this->pageService->expandPathsAndTemplateFileToTemplatePathAndFilename($paths, $template);
					if (FALSE === file_exists($templatePathAndFilename)) {
						$this->configurationService->message('Missing template file: ' . $templatePathAndFilename, GeneralUtility::SYSLOG_SEVERITY_WARNING);
						continue;
					}
					$form = $this->configurationService->getFormFromTemplateFile($templatePathAndFilename, 'Configuration', 'form', $paths, $extensionName);
					if (FALSE === $form instanceof Form) {
						$this->configurationService->message('Template file ' . $templatePathAndFilename . ' contains an unparsable Form definition', GeneralUtility::SYSLOG_SEVERITY_FATAL);
						continue;
					}
					if (FALSE === $form->getEnabled()) {
						$this->configurationService->message('Template file ' . $templatePathAndFilename . ' is disabled by configuration', GeneralUtility::SYSLOG_SEVERITY_NOTICE);
						continue;
					}
					$thumbnail = MiscellaneousUtility::getIconForTemplate($form);
					$label = $form->getLabel();
					$translatedLabel = LocalizationUtility::translate($label, $extensionName);
					if (NULL !== $translatedLabel) {
						$label = $translatedLabel;
					}
					$optionValue = $extension . '->' . $template;
					$selected = ($optionValue == $value ? ' checked="checked"' : '');
					$option = '<label style="padding: 0.5em; border: 1px solid #CCC; display: inline-block; vertical-align: bottom; margin: 0 1em 1em 0; cursor: pointer; ' . ($selected ? 'background-color: #DDD;' : '')  . '">';
					$option .= '<img src="' . $thumbnail . '" alt="' . $label . '" style="margin: 0.5em 0 0.5em 0; max-width: 196px; max-height: 128px;"/><br />';
					$option .= '<input type="radio" value="' . $optionValue . '"' . $selected . ' name="' . $name . '" ' . $onChange . ' /> ' . $label;
					$option .= '</label>';
					$selector .= $option . LF;
				} catch (\Exception $error) {
					$this->configurationService->debug($error);
				}
			}
		}
		$selector .= '</div>' . LF;
		unset($pObj);
		return $selector;
	}

}
