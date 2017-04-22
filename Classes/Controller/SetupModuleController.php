<?php
namespace TYPO3\CMS\Setup\Controller;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Backend\Avatar\DefaultAvatarProvider;
use TYPO3\CMS\Backend\Module\AbstractModule;
use TYPO3\CMS\Backend\Module\ModuleLoader;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Saltedpasswords\Salt\SaltFactory;

/**
 * Script class for the Setup module
 */
class SetupModuleController extends AbstractModule
{
    /**
     * Flag if password has not been updated
     */
    const PASSWORD_NOT_UPDATED = 0;

    /**
     * Flag if password has been updated
     */
    const PASSWORD_UPDATED = 1;

    /**
     * Flag if both new passwords do not match
     */
    const PASSWORD_NOT_THE_SAME = 2;

    /**
     * Flag if the current password given was not identical to the real
     * current password
     */
    const PASSWORD_OLD_WRONG = 3;

    /**
     * @var array
     */
    public $MOD_MENU = [];

    /**
     * @var array
     */
    public $MOD_SETTINGS = [];

    /**
     * @var string
     */
    public $content;

    /**
     * @var array
     */
    public $overrideConf;

    /**
     * backend user object, set during simulate-user operation
     *
     * @var \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    public $OLD_BE_USER;

    /**
     * @var bool
     */
    public $languageUpdate;

    /**
     * @var bool
     */
    protected $pagetreeNeedsRefresh = false;

    /**
     * @var bool
     */
    protected $isAdmin;

    /**
     * @var array
     */
    protected $tsFieldConf;

    /**
     * @var bool
     */
    protected $saveData = false;

    /**
     * @var int
     */
    protected $passwordIsUpdated = self::PASSWORD_NOT_UPDATED;

    /**
     * @var bool
     */
    protected $passwordIsSubmitted = false;

    /**
     * @var bool
     */
    protected $setupIsUpdated = false;

    /**
     * @var bool
     */
    protected $settingsAreResetToDefault = false;

    /**
     * Form protection instance
     *
     * @var \TYPO3\CMS\Core\FormProtection\BackendFormProtection
     */
    protected $formProtection;

    /**
     * @var string
     */
    protected $simulateSelector = '';

    /**
     * @var int
     */
    protected $simUser;

    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'user_setup';

    /**
     * @var ModuleLoader
     */
    protected $loadModules;

    /**
     * @var BackendUserAuthentication
     */
    protected $beUser;

    /**
     * Instantiate the form protection before a simulated user is initialized.
     */
    public function __construct()
    {
        parent::__construct();
        $this->formProtection = FormProtectionFactory::get();
        $pageRenderer = $this->moduleTemplate->getPageRenderer();
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Modal');
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/FormEngine');
        $pageRenderer->addInlineSetting('FormEngine', 'formName', 'editform');
        $pageRenderer->addInlineLanguageLabelArray([
            'FormEngine.remainingCharacters'    => 'LLL:EXT:lang/Resources/Private/Language/locallang_core.xlf:labels.remainingCharacters',
        ], true);
    }

    /**
     * Getter for the form protection instance.
     *
     * @return \TYPO3\CMS\Core\FormProtection\BackendFormProtection
     */
    public function getFormProtection()
    {
        return $this->formProtection;
    }

    /**
     * If settings are submitted to _POST[DATA], store them
     * NOTICE: This method is called before the \TYPO3\CMS\Backend\Template\ModuleTemplate
     * is included. See bottom of document.
     */
    public function storeIncomingData()
    {
        // First check if something is submitted in the data-array from POST vars
        $d = GeneralUtility::_POST('data');
        $columns = $GLOBALS['TYPO3_USER_SETTINGS']['columns'];
        $beUserId = $this->beUser->user['uid'];
        $storeRec = [];
        $fieldList = $this->getFieldsFromShowItem();
        if (is_array($d) && $this->formProtection->validateToken((string)GeneralUtility::_POST('formToken'), 'BE user setup', 'edit')) {
            // UC hashed before applying changes
            $save_before = md5(serialize($this->beUser->uc));
            // PUT SETTINGS into the ->uc array:
            // Reload left frame when switching BE language
            if (isset($d['lang']) && $d['lang'] != $this->beUser->uc['lang']) {
                $this->languageUpdate = true;
            }
            // Reload pagetree if the title length is changed
            if (isset($d['titleLen']) && $d['titleLen'] !== $this->beUser->uc['titleLen']) {
                $this->pagetreeNeedsRefresh = true;
            }
            if ($d['setValuesToDefault']) {
                // If every value should be default
                $this->beUser->resetUC();
                $this->settingsAreResetToDefault = true;
            } elseif ($d['save']) {
                // Save all submitted values if they are no array (arrays are with table=be_users) and exists in $GLOBALS['TYPO3_USER_SETTINGS'][columns]
                foreach ($columns as $field => $config) {
                    if (!in_array($field, $fieldList)) {
                        continue;
                    }
                    if ($config['table']) {
                        if ($config['table'] === 'be_users' && !in_array($field, ['password', 'password2', 'passwordCurrent', 'email', 'realName', 'admin', 'avatar'])) {
                            if (!isset($config['access']) || $this->checkAccess($config) && $this->beUser->user[$field] !== $d['be_users'][$field]) {
                                if ($config['type'] === 'check') {
                                    $fieldValue = isset($d['be_users'][$field]) ? 1 : 0;
                                } else {
                                    $fieldValue = $d['be_users'][$field];
                                }
                                $storeRec['be_users'][$beUserId][$field] = $fieldValue;
                                $this->beUser->user[$field] = $fieldValue;
                            }
                        }
                    }
                    if ($config['type'] === 'check') {
                        $this->beUser->uc[$field] = isset($d[$field]) ? 1 : 0;
                    } else {
                        $this->beUser->uc[$field] = htmlspecialchars($d[$field]);
                    }
                }
                // Personal data for the users be_user-record (email, name, password...)
                // If email and name is changed, set it in the users record:
                $be_user_data = $d['be_users'];
                // Possibility to modify the transmitted values. Useful to do transformations, like RSA password decryption
                if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/setup/mod/index.php']['modifyUserDataBeforeSave'])) {
                    foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/setup/mod/index.php']['modifyUserDataBeforeSave'] as $function) {
                        $params = ['be_user_data' => &$be_user_data];
                        GeneralUtility::callUserFunction($function, $params, $this);
                    }
                }
                $this->passwordIsSubmitted = (string)$be_user_data['password'] !== '';
                $passwordIsConfirmed = $this->passwordIsSubmitted && $be_user_data['password'] === $be_user_data['password2'];
                // Update the real name:
                if ($be_user_data['realName'] !== $this->beUser->user['realName']) {
                    $this->beUser->user['realName'] = ($storeRec['be_users'][$beUserId]['realName'] = substr($be_user_data['realName'], 0, 80));
                }
                // Update the email address:
                if ($be_user_data['email'] !== $this->beUser->user['email']) {
                    $this->beUser->user['email'] = ($storeRec['be_users'][$beUserId]['email'] = substr($be_user_data['email'], 0, 80));
                }
                // Update the password:
                if ($passwordIsConfirmed) {
                    if ($this->isAdmin) {
                        $passwordOk = true;
                    } else {
                        $currentPasswordHashed = $GLOBALS['BE_USER']->user['password'];
                        $saltFactory = SaltFactory::getSaltingInstance($currentPasswordHashed);
                        $passwordOk = $saltFactory->checkPassword($be_user_data['passwordCurrent'], $currentPasswordHashed);
                    }
                    if ($passwordOk) {
                        $this->passwordIsUpdated = self::PASSWORD_UPDATED;
                        $storeRec['be_users'][$beUserId]['password'] = $be_user_data['password'];
                    } else {
                        $this->passwordIsUpdated = self::PASSWORD_OLD_WRONG;
                    }
                } else {
                    $this->passwordIsUpdated = self::PASSWORD_NOT_THE_SAME;
                }

                $this->setAvatarFileUid($beUserId, $be_user_data['avatar'], $storeRec);

                $this->saveData = true;
            }
            // Inserts the overriding values.
            $this->beUser->overrideUC();
            $save_after = md5(serialize($this->beUser->uc));
            // If something in the uc-array of the user has changed, we save the array...
            if ($save_before != $save_after) {
                $this->beUser->writeUC($this->beUser->uc);
                $this->beUser->writelog(254, 1, 0, 1, 'Personal settings changed', []);
                $this->setupIsUpdated = true;
            }
            // Persist data if something has changed:
            if (!empty($storeRec) && $this->saveData) {
                // Make instance of TCE for storing the changes.
                /** @var DataHandler $dataHandler */
                $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                // This is so the user can actually update his user record.
                $realUser = $this->getRealScriptUserObj();
                $isAdmin = $realUser->user['admin'];
                $realUser->user['admin'] = 1;
                $dataHandler->start($storeRec, [], $realUser);
                // This is to make sure that the users record can be updated even if in another workspace. This is tolerated.
                $dataHandler->bypassWorkspaceRestrictions = true;
                $dataHandler->process_datamap();
                unset($tce);
                if ($this->passwordIsUpdated === self::PASSWORD_NOT_UPDATED || count($storeRec['be_users'][$beUserId]) > 1) {
                    $this->setupIsUpdated = true;
                }
                // Restore admin status after processing
                $realUser->user['admin'] = $isAdmin;

                BackendUtility::setUpdateSignal('updateTopbar');
            }
        }
    }

    /******************************
     *
     * Rendering module
     *
     ******************************/
    /**
     * Initializes the module for display of the settings form.
     */
    public function init()
    {
        $this->getLanguageService()->includeLLFile('EXT:setup/Resources/Private/Language/locallang.xlf');

        // Returns the script user - that is the REAL logged in user! ($GLOBALS[BE_USER] might be another user due to simulation!)
        $scriptUser = $this->getRealScriptUserObj();

        $this->isAdmin = $scriptUser->isAdmin();
        // Getting the 'override' values as set might be set in User TSconfig
        $this->overrideConf = $this->beUser->getTSConfigProp('setup.override');
        // Getting the disabled fields might be set in User TSconfig (eg setup.fields.password.disabled=1)
        $this->tsFieldConf = $this->beUser->getTSConfigProp('setup.fields');
        // id password is disabled, disable repeat of password too (password2)
        if (isset($this->tsFieldConf['password.']) && $this->tsFieldConf['password.']['disabled']) {
            $this->tsFieldConf['password2.']['disabled'] = 1;
            $this->tsFieldConf['passwordCurrent.']['disabled'] = 1;
        }
    }

    /**
     * Generate necessary JavaScript
     *
     * @return string
     */
    protected function getJavaScript()
    {
        $javaScript = '';
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/setup/mod/index.php']['setupScriptHook'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/setup/mod/index.php']['setupScriptHook'] as $function) {
                $params = [];
                $javaScript .= GeneralUtility::callUserFunction($function, $params, $this);
            }
        }
        return $javaScript;
    }

    /**
     * Generate the main settings form:
     */
    public function main()
    {
        $this->content .= '<form action="' . BackendUtility::getModuleUrl('user_setup') . '" method="post" id="SetupModuleController" name="usersetup" enctype="multipart/form-data">';
        if ($this->languageUpdate) {
            $this->moduleTemplate->addJavaScriptCode('languageUpdate', '
                if (top && top.TYPO3.ModuleMenu.App) {
                    top.TYPO3.ModuleMenu.App.refreshMenu();
                }
            ');
        }
        if ($this->pagetreeNeedsRefresh) {
            BackendUtility::setUpdateSignal('updatePageTree');
        }
        // Start page:
        $this->moduleTemplate->getPageRenderer()->addJsFile('EXT:backend/Resources/Public/JavaScript/md5.js');
        // Use a wrapper div
        $this->content .= '<div id="user-setup-wrapper">';
        // Load available backend modules
        $this->loadModules = GeneralUtility::makeInstance(ModuleLoader::class);
        $this->loadModules->observeWorkspaces = true;
        $this->loadModules->load($GLOBALS['TBE_MODULES']);
        $this->content .= $this->moduleTemplate->header($this->getLanguageService()->getLL('UserSettings'));
        $this->addFlashMessages();

        // Render user switch
        $this->content .= $this->renderSimulateUserSelectAndLabel();

        // Render the menu items
        $menuItems = $this->renderUserSetup();
        $this->content .= $this->moduleTemplate->getDynamicTabMenu($menuItems, 'user-setup', 1, false, false);
        $formToken = $this->formProtection->generateToken('BE user setup', 'edit');
        $this->content .= '<div>';
        $this->content .= '<input type="hidden" name="simUser" value="' . (int)$this->simUser . '" />
            <input type="hidden" name="formToken" value="' . htmlspecialchars($formToken) . '" />
            <input type="hidden" value="1" name="data[save]" />
            <input type="hidden" name="data[setValuesToDefault]" value="0" id="setValuesToDefault" />';
        $this->content .= '</div>';
        // End of wrapper div
        $this->content .= '</div>';
        // Setting up the buttons and markers for docheader
        $this->getButtons();
        // Build the <body> for the module
        // Renders the module page
        $this->moduleTemplate->setContent($this->content);
        $this->content .= '</form>';
    }

    /**
     * Injects the request object for the current request or subrequest
     * Simply calls main() and init() and writes the content to the response
     *
     * @param ServerRequestInterface $request the current request
     * @param ResponseInterface $response
     * @return ResponseInterface the response with the content
     */
    public function mainAction(ServerRequestInterface $request, ResponseInterface $response)
    {
        $GLOBALS['SOBE'] = $this;
        $this->simulateUser();
        $this->init();
        $this->storeIncomingData();
        $this->main();

        $response->getBody()->write($this->moduleTemplate->renderContent());
        return $response;
    }

    /**
     * Create the panel of buttons for submitting the form or otherwise perform operations.
     */
    protected function getButtons()
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $cshButton = $buttonBar->makeHelpButton()
            ->setModuleName('_MOD_user_setup')
            ->setFieldName('');
        $buttonBar->addButton($cshButton);

        $saveButton = $buttonBar->makeInputButton()
            ->setName('data[save]')
            ->setTitle($this->getLanguageService()->sL('LLL:EXT:lang/Resources/Private/Language/locallang_core.xlf:rm.saveDoc'))
            ->setValue('1')
            ->setForm('SetupModuleController')
            ->setShowLabelText(true)
            ->setIcon($this->moduleTemplate->getIconFactory()->getIcon('actions-document-save', Icon::SIZE_SMALL));

        $buttonBar->addButton($saveButton);
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setModuleName($this->moduleName);
        $buttonBar->addButton($shortcutButton);
    }

    /******************************
     *
     * Render module
     *
     ******************************/

    /**
     * renders the data for all tabs in the user setup and returns
     * everything that is needed with tabs and dyntab menu
     *
     * @return array Ready to use for the dyntabmenu itemarray
     */
    protected function renderUserSetup()
    {
        $html = '';
        $result = [];
        $firstTabLabel = '';
        $code = [];
        $fieldArray = $this->getFieldsFromShowItem();
        $tabLabel = '';
        foreach ($fieldArray as $fieldName) {
            $config = $GLOBALS['TYPO3_USER_SETTINGS']['columns'][$fieldName];
            if (isset($config['access']) && !$this->checkAccess($config)) {
                continue;
            }

            if (substr($fieldName, 0, 8) === '--div--;') {
                if ($firstTabLabel === '') {
                    // First tab
                    $tabLabel = $this->getLabel(substr($fieldName, 8), '', false);
                    $firstTabLabel = $tabLabel;
                } else {
                    $result[] = [
                        'label' => $tabLabel,
                        'content' => count($code) ? implode(LF, $code) : ''
                    ];
                    $tabLabel = $this->getLabel(substr($fieldName, 8), '', false);
                    $code = [];
                }
                continue;
            }
            $label = $this->getLabel($config['label'], $fieldName);
            $label = $this->getCSH($config['csh'] ?: $fieldName, $label);
            $type = $config['type'];
            $class = $config['class'];
            if ($type !== 'check') {
                $class .= ' form-control';
            }
            $more = '';
            if ($class) {
                $more .= ' class="' . htmlspecialchars($class) . '"';
            }
            $style = $config['style'];
            if ($style) {
                $more .= ' style="' . htmlspecialchars($style) . '"';
            }
            if (isset($this->overrideConf[$fieldName])) {
                $more .= ' disabled="disabled"';
            }
            $value = $config['table'] === 'be_users' ? $this->beUser->user[$fieldName] : $this->beUser->uc[$fieldName];
            if (!$value && isset($config['default'])) {
                $value = $config['default'];
            }
            $dataAdd = '';
            if ($config['table'] === 'be_users') {
                $dataAdd = '[be_users]';
            }

            switch ($type) {
                case 'text':
                case 'email':
                case 'password':
                    $noAutocomplete = '';

                    $maxLength = $config['max'] ?? 0;
                    if ((int)$maxLength > 0) {
                        $more .= ' maxlength="' . (int)$maxLength . '"';
                    }

                    if ($type === 'password') {
                        $value = '';
                        $noAutocomplete = 'autocomplete="off" ';
                        $more .= ' data-rsa-encryption=""';
                    }
                    $html = '<input id="field_' . htmlspecialchars($fieldName) . '"
                        type="' . htmlspecialchars($type) . '"
                        name="data' . $dataAdd . '[' . htmlspecialchars($fieldName) . ']" ' .
                        $noAutocomplete .
                        'value="' . htmlspecialchars($value) . '" ' .
                        $more .
                        ' />';
                    break;
                case 'check':
                    $html = $label . '<div class="checkbox"><label><input id="field_' . htmlspecialchars($fieldName) . '"
                        type="checkbox"
                        name="data' . $dataAdd . '[' . htmlspecialchars($fieldName) . ']"' .
                        ($value ? ' checked="checked"' : '') .
                        $more .
                        ' /></label></div>';
                    $label = '';
                    break;
                case 'select':
                    if ($config['itemsProcFunc']) {
                        $html = GeneralUtility::callUserFunction($config['itemsProcFunc'], $config, $this);
                    } else {
                        $html = '<select id="field_' . htmlspecialchars($fieldName) . '"
                            name="data' . $dataAdd . '[' . htmlspecialchars($fieldName) . ']"' .
                            $more . '>' . LF;
                        foreach ($config['items'] as $key => $optionLabel) {
                            $html .= '<option value="' . htmlspecialchars($key) . '"' . ($value == $key ? ' selected="selected"' : '') . '>' . $this->getLabel($optionLabel, '', false) . '</option>' . LF;
                        }
                        $html .= '</select>';
                    }
                    break;
                case 'user':
                    $html = GeneralUtility::callUserFunction($config['userFunc'], $config, $this);
                    break;
                case 'button':
                    if ($config['onClick']) {
                        $onClick = $config['onClick'];
                        if ($config['onClickLabels']) {
                            foreach ($config['onClickLabels'] as $key => $labelclick) {
                                $config['onClickLabels'][$key] = $this->getLabel($labelclick, '', false);
                            }
                            $onClick = vsprintf($onClick, $config['onClickLabels']);
                        }
                        $html = '<br><input class="btn btn-default" type="button"
                            value="' . $this->getLabel($config['buttonlabel'], '', false) . '"
                            onclick="' . $onClick . '" />';
                    }
                    if (!empty($config['confirm'])) {
                        $confirmData = $config['confirmData'];
                        $html = '<br><input class="btn btn-default t3js-modal-trigger" type="button"'
                            . ' value="' . $this->getLabel($config['buttonlabel'], '', false) . '"'
                            . ' data-href="javascript:' . htmlspecialchars($confirmData['jsCodeAfterOk']) . '"'
                            . ' data-severity="warning"'
                            . ' data-title="' . $this->getLabel($config['label'], '', false) . '"'
                            . ' data-content="' . $this->getLabel($confirmData['message'], '', false) . '" />';
                    }
                    break;
                case 'avatar':
                    // Get current avatar image
                    $html = '<br>';
                    $avatarFileUid = $this->getAvatarFileUid($this->beUser->user['uid']);

                    if ($avatarFileUid) {
                        $defaultAvatarProvider = GeneralUtility::makeInstance(DefaultAvatarProvider::class);
                        $avatarImage = $defaultAvatarProvider->getImage($this->beUser->user, 32);
                        if ($avatarImage) {
                            $icon = '<span class="avatar"><span class="avatar-image">' .
                                '<img src="' . htmlspecialchars($avatarImage->getUrl(true)) . '"' .
                                ' width="' . (int)$avatarImage->getWidth() . '" ' .
                                'height="' . (int)$avatarImage->getHeight() . '" />' .
                                '</span></span>';
                            $html .= '<span class="pull-left" style="padding-right: 10px" id="image_' . htmlspecialchars($fieldName) . '">' . $icon . ' </span>';
                        }
                    }
                    $html .= '<input id="field_' . htmlspecialchars($fieldName) . '" type="hidden" ' .
                            'name="data' . $dataAdd . '[' . htmlspecialchars($fieldName) . ']"' . $more .
                            ' value="' . (int)$avatarFileUid . '" />';

                    $html .= '<div class="btn-group">';
                    $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
                    if ($avatarFileUid) {
                        $html .=
                            '<a id="clear_button_' . htmlspecialchars($fieldName) . '" '
                                . 'onclick="clearExistingImage(); return false;" class="btn btn-default">'
                                . $iconFactory->getIcon('actions-delete', Icon::SIZE_SMALL)
                            . '</a>';
                    }
                    $html .=
                        '<a id="add_button_' . htmlspecialchars($fieldName) . '" class="btn btn-default btn-add-avatar"'
                            . ' onclick="openFileBrowser();return false;">'
                            . $iconFactory->getIcon('actions-insert-record', Icon::SIZE_SMALL)
                            . '</a></div>';

                    $this->addAvatarButtonJs($fieldName);
                    break;
                default:
                    $html = '';
            }

            $code[] = '<div class="form-section"><div class="row"><div class="form-group t3js-formengine-field-item col-md-12">' .
                $label .
                $html .
                '</div></div></div>';
        }

        $result[] = [
            'label' => $tabLabel,
            'content' => count($code) ? implode(LF, $code) : ''
        ];
        return $result;
    }

    /******************************
     *
     * Helper functions
     *
     ******************************/
    /**
     * Returns the backend user object, either the global OR the $this->OLD_BE_USER which is set during simulate-user operation.
     * Anyway: The REAL user is returned - the one logged in.
     *
     * @return BackendUserAuthentication The REAL user is returned - the one logged in.
     */
    protected function getRealScriptUserObj()
    {
        return is_object($this->OLD_BE_USER) ? $this->OLD_BE_USER : $this->beUser;
    }

    /**
     * Return a select with available languages
     *
     * @param array $params unused
     * @param SetupModuleController $controller
     * @return string Complete select as HTML string or warning box if something went wrong.
     */
    public function renderLanguageSelect(array $params, $controller)
    {
        $languageOptions = [];
        // Compile the languages dropdown
        $langDefault = htmlspecialchars($this->getLanguageService()->getLL('lang_default'));
        $languageOptions[$langDefault] = '<option value=""' . ($controller->beUser->uc['lang'] === '' ? ' selected="selected"' : '') . '>' . $langDefault . '</option>';
        // Traverse the number of languages
        /** @var $locales \TYPO3\CMS\Core\Localization\Locales */
        $locales = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Localization\Locales::class);
        $languages = $locales->getLanguages();
        foreach ($languages as $locale => $name) {
            if ($locale !== 'default') {
                $defaultName = isset($GLOBALS['LOCAL_LANG']['default']['lang_' . $locale]) ? $GLOBALS['LOCAL_LANG']['default']['lang_' . $locale][0]['source'] : $name;
                $localizedName = htmlspecialchars($this->getLanguageService()->getLL('lang_' . $locale));
                if ($localizedName === '') {
                    $localizedName = htmlspecialchars($name);
                }
                $localLabel = '  -  [' . htmlspecialchars($defaultName) . ']';
                $available = is_dir(PATH_typo3conf . 'l10n/' . $locale);
                if ($available) {
                    $languageOptions[$defaultName] = '<option value="' . $locale . '"' . ($controller->beUser->uc['lang'] === $locale ? ' selected="selected"' : '') . '>' . $localizedName . $localLabel . '</option>';
                }
            }
        }
        ksort($languageOptions);
        $languageCode = '
            <select id="field_lang" name="data[lang]" class="form-control">' . implode('', $languageOptions) . '
            </select>';
        if ($controller->beUser->uc['lang'] && !@is_dir((PATH_typo3conf . 'l10n/' . $controller->beUser->uc['lang']))) {
            // TODO: The text constants have to be moved into language files
            $languageUnavailableWarning = 'The selected language "' . htmlspecialchars($this->getLanguageService()->getLL('lang_' . $controller->beUser->uc['lang'])) . '" is not available before the language files are installed.&nbsp;&nbsp;<br />&nbsp;&nbsp;' . ($controller->beUser->isAdmin() ? 'You can use the Language module to easily download new language files.' : 'Please ask your system administrator to do this.');
            $languageCode = '<br /><span class="label label-danger">' . $languageUnavailableWarning . '</span><br /><br />' . $languageCode;
        }
        return $languageCode;
    }

    /**
     * Returns a select with all modules for startup
     *
     * @param array $params
     * @param SetupModuleController $pObj
     *
     * @return string Complete select as HTML string
     */
    public function renderStartModuleSelect($params, $pObj)
    {
        // Load available backend modules
        $this->loadModules = GeneralUtility::makeInstance(ModuleLoader::class);
        $this->loadModules->observeWorkspaces = true;
        $this->loadModules->load($GLOBALS['TBE_MODULES']);
        $startModuleSelect = '<option value="">' . htmlspecialchars($this->getLanguageService()->getLL('startModule.firstInMenu')) . '</option>';
        foreach ($pObj->loadModules->modules as $mainMod => $modData) {
            if (!empty($modData['sub']) && is_array($modData['sub'])) {
                $modules = '';
                foreach ($modData['sub'] as $subData) {
                    $modName = $subData['name'];
                    $modules .= '<option value="' . htmlspecialchars($modName) . '"';
                    $modules .= $pObj->beUser->uc['startModule'] === $modName ? ' selected="selected"' : '';
                    $modules .= '>' . htmlspecialchars($this->getLanguageService()->sL($this->loadModules->getLabelsForModule($modName)['title'])) . '</option>';
                }
                $groupLabel = htmlspecialchars($this->getLanguageService()->sL($this->loadModules->getLabelsForModule($mainMod)['title']));
                $startModuleSelect .= '<optgroup label="' . htmlspecialchars($groupLabel) . '">' . $modules . '</optgroup>';
            }
        }
        return '<select id="field_startModule" name="data[startModule]" class="form-control">' . $startModuleSelect . '</select>';
    }

    /**
     * Will make the simulate-user selector if the logged in user is administrator.
     * It will also set the GLOBAL(!) BE_USER to the simulated user selected if any (and set $this->OLD_BE_USER to logged in user)
     */
    public function simulateUser()
    {
        // If admin, allow simulation of another user
        $this->simUser = 0;
        $this->simulateSelector = '';
        unset($this->OLD_BE_USER);
        $currentBeUser = $this->getBackendUser();
        if ($currentBeUser->isAdmin()) {
            $this->simUser = (int)GeneralUtility::_GP('simUser');
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_users');
            $users = $queryBuilder
                ->select('*')
                ->from('be_users')
                ->where(
                    $queryBuilder->expr()->neq(
                        'uid',
                        $queryBuilder->createNamedParameter($currentBeUser->user['uid'], \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->notLike(
                        'username',
                        $queryBuilder->createNamedParameter(
                            $queryBuilder->escapeLikeWildcards('_cli_') . '%',
                            \PDO::PARAM_STR
                        )
                    )
                )
                ->orderBy('username')
                ->execute()
                ->fetchAll();
            $opt = [];
            foreach ($users as $rr) {
                $label = $rr['username'] . ($rr['realName'] ? ' (' . $rr['realName'] . ')' : '');
                $opt[] = '<option value="' . (int)$rr['uid'] . '"' . ($this->simUser === (int)$rr['uid'] ? ' selected="selected"' : '') . '>' . htmlspecialchars($label) . '</option>';
            }
            if (!empty($opt)) {
                $this->simulateSelector = '<select id="field_simulate" class="form-control" name="simulateUser" onchange="window.location.href=' . GeneralUtility::quoteJSvalue(BackendUtility::getModuleUrl('user_setup') . '&simUser=') . '+this.options[this.selectedIndex].value;"><option></option>' . implode('', $opt) . '</select>';
            }
        }
        // This can only be set if the previous code was executed.
        if ($this->simUser > 0) {
            // Save old user...
            $this->OLD_BE_USER = $currentBeUser;
            // Unset current
            // New backend user object
            $currentBeUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
            $currentBeUser->setBeUserByUid($this->simUser);
            $currentBeUser->fetchGroupData();
            $currentBeUser->backendSetUC();
        }
        $this->beUser = $currentBeUser;
    }

    /**
     * Render simulate user select and label
     *
     * @return string
     */
    protected function renderSimulateUserSelectAndLabel()
    {
        if ($this->simulateSelector === '') {
            return '';
        }

        return '<div class="form-inline"><div class="form-group"><p>'
             . '<label for="field_simulate" style="margin-right: 20px;">'
             . htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:setup/Resources/Private/Language/locallang.xlf:simulate'))
             . '</label>'
             . $this->simulateSelector
             . '</p></div></div>';
    }

    /**
     * Returns access check (currently only "admin" is supported)
     *
     * @param array $config Configuration of the field, access mode is defined in key 'access'
     * @return bool Whether it is allowed to modify the given field
     */
    protected function checkAccess(array $config)
    {
        $access = $config['access'];

        if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['setup']['accessLevelCheck'][$access])) {
            if (class_exists($access)) {
                $accessObject = GeneralUtility::makeInstance($access);
                if (method_exists($accessObject, 'accessLevelCheck')) {
                    // Initialize vars. If method fails, $set will be set to FALSE
                    return $accessObject->accessLevelCheck($config);
                }
            }
        } elseif ($access === 'admin') {
            return $this->isAdmin;
        }

        return false;
    }

    /**
     * Returns the label $str from getLL() and grays out the value if the $str/$key is found in $this->overrideConf array
     *
     * @param string $str Locallang key
     * @param string $key Alternative override-config key
     * @param bool $addLabelTag Defines whether the string should be wrapped in a <label> tag.
     * @return string HTML output.
     */
    protected function getLabel($str, $key = '', $addLabelTag = true)
    {
        if (substr($str, 0, 4) === 'LLL:') {
            $out = htmlspecialchars($this->getLanguageService()->sL($str));
        } else {
            $out = htmlspecialchars($str);
        }
        if (isset($this->overrideConf[$key ?: $str])) {
            $out = '<span style="color:#999999">' . $out . '</span>';
        }
        if ($addLabelTag) {
            $out = '<label>' . $out . '</label>';
        }
        return $out;
    }

    /**
     * Returns the CSH Icon for given string
     *
     * @param string $str Locallang key
     * @param string $label The label to be used, that should be wrapped in help
     * @return string HTML output.
     */
    protected function getCSH($str, $label)
    {
        $context = '_MOD_user_setup';
        $field = $str;
        $strParts = explode(':', $str);
        if (count($strParts) > 1) {
            // Setting comes from another extension
            $context = $strParts[0];
            $field = $strParts[1];
        } elseif ($str !== 'language' && $str !== 'simuser' && $str !== 'reset') {
            $field = 'option_' . $str;
        }
        return BackendUtility::wrapInHelp($context, $field, $label);
    }

    /**
     * Returns array with fields defined in $GLOBALS['TYPO3_USER_SETTINGS']['showitem']
     * Remove fields which are disabled by user TSconfig
     *
     * @return string[] Array with field names visible in form
     */
    protected function getFieldsFromShowItem()
    {
        $allowedFields = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_USER_SETTINGS']['showitem'], true);
        // do not ask for current password if admin (unknown for other users and no security gain)
        if ($this->isAdmin) {
            $key = array_search('passwordCurrent', $allowedFields);
            if ($key !== false) {
                unset($allowedFields[$key]);
            }
        }
        if (!is_array($this->tsFieldConf)) {
            return $allowedFields;
        }
        foreach ($this->tsFieldConf as $fieldName => $userTsFieldConfig) {
            if (!empty($userTsFieldConfig['disabled'])) {
                $fieldName = rtrim($fieldName, '.');
                $key = array_search($fieldName, $allowedFields);
                if ($key !== false) {
                    unset($allowedFields[$key]);
                }
            }
        }
        return $allowedFields;
    }

    /**
     * Get Avatar fileUid
     *
     * @param int $beUserId
     * @return int
     */
    protected function getAvatarFileUid($beUserId)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $file = $queryBuilder
            ->select('uid_local')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq(
                    'tablenames',
                    $queryBuilder->createNamedParameter('be_users', \PDO::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'fieldname',
                    $queryBuilder->createNamedParameter('avatar', \PDO::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'table_local',
                    $queryBuilder->createNamedParameter('sys_file', \PDO::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'uid_foreign',
                    $queryBuilder->createNamedParameter($beUserId, \PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetchColumn();
        return (int)$file;
    }

    /**
     * Set avatar fileUid for backend user
     *
     * @param int $beUserId
     * @param int $fileUid
     * @param array $storeRec
     */
    protected function setAvatarFileUid($beUserId, $fileUid, array &$storeRec)
    {

        // Update is only needed when new fileUid is set
        if ((int)$fileUid === $this->getAvatarFileUid($beUserId)) {
            return;
        }

        // If user is not allowed to modify avatar $fileUid is empty - so don't overwrite existing avatar
        if (empty($fileUid)) {
            return;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->delete('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq(
                    'tablenames',
                    $queryBuilder->createNamedParameter('be_users', \PDO::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'fieldname',
                    $queryBuilder->createNamedParameter('avatar', \PDO::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'table_local',
                    $queryBuilder->createNamedParameter('sys_file', \PDO::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'uid_foreign',
                    $queryBuilder->createNamedParameter($beUserId, \PDO::PARAM_INT)
                )
            )
            ->execute();

        // If Avatar is marked for delete => set it to empty string so it will be updated properly
        if ($fileUid === 'delete') {
            $fileUid = '';
        }

        // Create new reference
        if ($fileUid) {

            // Get file object
            try {
                $file = ResourceFactory::getInstance()->getFileObject($fileUid);
            } catch (FileDoesNotExistException $e) {
                $file = false;
            }

            // Check if user is allowed to use the image (only when not in simulation mode)
            if ($file && $this->simUser === 0 && !$file->getStorage()->checkFileActionPermission('read', $file)) {
                $file = false;
            }

            // Check if extension is allowed
            if ($file && GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], $file->getExtension())) {

                // Create new file reference
                $storeRec['sys_file_reference']['NEW1234'] = [
                    'uid_local' => (int)$fileUid,
                    'uid_foreign' => (int)$beUserId,
                    'tablenames' => 'be_users',
                    'fieldname' => 'avatar',
                    'pid' => 0,
                    'table_local' => 'sys_file',
                ];
                $storeRec['be_users'][(int)$beUserId]['avatar'] = 'NEW1234';
            }
        }
    }

    /**
     * Add JavaScript to for browse files button
     *
     * @param string $fieldName
     */
    protected function addAvatarButtonJs($fieldName)
    {
        $this->moduleTemplate->addJavaScriptCode('avatar-button', '
            var browserWin="";

            function openFileBrowser() {
                var url = ' . GeneralUtility::quoteJSvalue(BackendUtility::getModuleUrl('wizard_element_browser', ['mode' => 'file', 'bparams' => '||||dummy|setFileUid'])) . ';
                browserWin = window.open(url,"Typo3WinBrowser","height=650,width=800,status=0,menubar=0,resizable=1,scrollbars=1");
                browserWin.focus();
            }

            function clearExistingImage() {
                $(' . GeneralUtility::quoteJSvalue('#image_' . htmlspecialchars($fieldName)) . ').hide();
                $(' . GeneralUtility::quoteJSvalue('#clear_button_' . htmlspecialchars($fieldName)) . ').hide();
                $(' . GeneralUtility::quoteJSvalue('#field_' . htmlspecialchars($fieldName)) . ').val(\'delete\');
            }

            function setFileUid(field, value, fileUid) {
                clearExistingImage();
                $(' . GeneralUtility::quoteJSvalue('#field_' . htmlspecialchars($fieldName)) . ').val(fileUid);
                $(' . GeneralUtility::quoteJSvalue('#add_button_' . htmlspecialchars($fieldName)) . ').removeClass(\'btn-default\').addClass(\'btn-info\');

                browserWin.close();
            }
        ');
    }

    /**
     * Returns the current BE user.
     *
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Returns LanguageService
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Add FlashMessages for various actions
     */
    protected function addFlashMessages()
    {
        $flashMessages = [];

        // Show if setup was saved
        if ($this->setupIsUpdated && !$this->settingsAreResetToDefault) {
            $flashMessages[] = $this->getFlashMessage('setupWasUpdated', 'UserSettings');
        }

        // Show if temporary data was cleared
        if ($this->settingsAreResetToDefault) {
            $flashMessages[] = $this->getFlashMessage('settingsAreReset', 'resetConfiguration');
        }

        // Notice
        if ($this->setupIsUpdated || $this->settingsAreResetToDefault) {
            $flashMessages[] = $this->getFlashMessage('activateChanges', '', FlashMessage::INFO);
        }

        // If password is updated, output whether it failed or was OK.
        if ($this->passwordIsSubmitted) {
            $flashMessage = null;
            switch ($this->passwordIsUpdated) {
                case self::PASSWORD_OLD_WRONG:
                    $flashMessages[] = $this->getFlashMessage('oldPassword_failed', 'newPassword', FlashMessage::ERROR);
                    break;
                case self::PASSWORD_NOT_THE_SAME:
                    $flashMessages[] = $this->getFlashMessage('newPassword_failed', 'newPassword', FlashMessage::ERROR);
                    break;
                case self::PASSWORD_UPDATED:
                    $flashMessages[] = $this->getFlashMessage('newPassword_ok', 'newPassword');
                    break;
            }
        }
        if (!empty($flashMessages)) {
            $this->enqueueFlashMessages($flashMessages);
        }
    }

    /**
     * @param array $flashMessages
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function enqueueFlashMessages(array $flashMessages)
    {
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        foreach ($flashMessages as $flashMessage) {
            $defaultFlashMessageQueue->enqueue($flashMessage);
        }
    }

    /**
     * @param string $message
     * @param string $title
     * @param int $severity
     * @return FlashMessage
     */
    protected function getFlashMessage($message, $title, $severity = FlashMessage::OK)
    {
        $title = !empty($title) ? $this->getLanguageService()->getLL($title) : ' ';
        return GeneralUtility::makeInstance(
            FlashMessage::class,
            $this->getLanguageService()->getLL($message),
            $title,
            $severity
        );
    }
}
