<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 * Based on
 *   LiveStreet Engine Social Networking by Mzhelskiy Maxim
 *   Site: www.livestreet.ru
 *   E-mail: rus.engine@gmail.com
 *----------------------------------------------------------------------------
 */

/**
 * Плагин для Smarty
 * Работа с группой виджетов
 *
 * @param   array $aParams
 * @param   Smarty_Internal_Template $oSmartyTemplate
 * @return  string
 */
function smarty_function_wgroup_add($aParams, $oSmartyTemplate) {

    if (!isset($aParams['group']) && !isset($aParams['name'])) {
        trigger_error('Parameter "group" or "name" does not define in {wgroup ...} function', E_USER_WARNING);
        return;
    } else {
        if (!isset($aParams['group']) && isset($aParams['name'])) {
            $aParams['group'] = $aParams['name'];
        }
    }
    if (!isset($aParams['widget'])) {
        trigger_error('Parameter "widget" does not define in {wgroup ...} function', E_USER_WARNING);
        return;
    }

    $aWidgetParams = (isset($aParams['params']) ? (array)$aParams['params'] : array());
    if (array_key_exists('priority', $aWidgetParams)) {
        $nPriority = $aWidgetParams['priority'];
    } elseif (array_key_exists('priority', $aParams)) {
        $nPriority = $aWidgetParams['priority'];
    } else {
        $nPriority = 0;
    }

    foreach ($aParams as $sKey => $sVal) {
        if (!in_array($sKey, array('group', 'name', 'widget', 'params', 'priority'))) {
            $aWidgetParams[$sKey] = $sVal;
        }
    }

    E::Viewer_AddWidget($aParams['group'], $aParams['widget'], $aWidgetParams, $nPriority);
    $aWidgets = E::Viewer_GetWidgets();

    $oSmartyTemplate->assign('aWidgets', $aWidgets);
    $oSmartyTemplate->parent->assign('aWidgets', $aWidgets);

    return '';
}

// EOF