<?php

defined('TYPO3') || die;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addStaticFile(
    'rmnd_brevo',
    'Configuration/TypoScript',
    'REMIND - Brevo'
);
