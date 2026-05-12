CREATE TABLE IF NOT EXISTS `b_realposterum_ai_task` (
    `ID` int unsigned NOT NULL AUTO_INCREMENT,
    `PRODUCT_ID` int unsigned NOT NULL,
    `SOURCE_IBLOCK_ID` int unsigned NOT NULL,
    `STATUS` varchar(32) NOT NULL DEFAULT 'new',
    `PAYLOAD_JSON` longtext NOT NULL,
    `PROMPT_TEXT` mediumtext DEFAULT NULL,
    `RESULT_JSON` longtext DEFAULT NULL,
    `ERROR_MESSAGE` text DEFAULT NULL,
    `CREATED_AT` datetime NOT NULL,
    `UPDATED_AT` datetime NOT NULL,
    `PROCESSED_AT` datetime DEFAULT NULL,
    `DECIDED_AT` datetime DEFAULT NULL,
    PRIMARY KEY (`ID`),
    KEY `ix_realposterum_ai_task_status` (`STATUS`),
    KEY `ix_realposterum_ai_task_product` (`PRODUCT_ID`, `SOURCE_IBLOCK_ID`)
);
