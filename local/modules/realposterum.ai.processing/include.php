<?php

declare(strict_types=1);

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(
    'realposterum.ai.processing',
    [
        'RealPosterum\\AiProcessing\\Api\\AiClient' => 'lib/Api/AiClient.php',
        'RealPosterum\\AiProcessing\\Contract\\HttpClientInterface' => 'lib/Contract/HttpClientInterface.php',
        'RealPosterum\\AiProcessing\\Dto\\ProcessingResult' => 'lib/Dto/ProcessingResult.php',
        'RealPosterum\\AiProcessing\\Enum\\TaskStatus' => 'lib/Enum/TaskStatus.php',
        'RealPosterum\\AiProcessing\\Infrastructure\\BitrixHttpClient' => 'lib/Infrastructure/BitrixHttpClient.php',
        'RealPosterum\\AiProcessing\\Model\\ProductSnapshot' => 'lib/Model/ProductSnapshot.php',
        'RealPosterum\\AiProcessing\\Repository\\ProcessingLogRepository' => 'lib/Repository/ProcessingLogRepository.php',
        'RealPosterum\\AiProcessing\\Repository\\ProcessingTaskRepository' => 'lib/Repository/ProcessingTaskRepository.php',
        'RealPosterum\\AiProcessing\\Service\\DecisionService' => 'lib/Service/DecisionService.php',
        'RealPosterum\\AiProcessing\\Service\\FieldCatalog' => 'lib/Service/FieldCatalog.php',
        'RealPosterum\\AiProcessing\\Service\\JsonPathResolver' => 'lib/Service/JsonPathResolver.php',
        'RealPosterum\\AiProcessing\\Service\\LogService' => 'lib/Service/LogService.php',
        'RealPosterum\\AiProcessing\\Service\\ModuleSettings' => 'lib/Service/ModuleSettings.php',
        'RealPosterum\\AiProcessing\\Service\\PayloadBuilder' => 'lib/Service/PayloadBuilder.php',
        'RealPosterum\\AiProcessing\\Service\\ProcessingFlagProvider' => 'lib/Service/ProcessingFlagProvider.php',
        'RealPosterum\\AiProcessing\\Service\\ProcessingService' => 'lib/Service/ProcessingService.php',
        'RealPosterum\\AiProcessing\\Service\\ProductDataExtractor' => 'lib/Service/ProductDataExtractor.php',
        'RealPosterum\\AiProcessing\\Service\\ProductSnapshotBuilder' => 'lib/Service/ProductSnapshotBuilder.php',
        'RealPosterum\\AiProcessing\\Service\\PromptBuilder' => 'lib/Service/PromptBuilder.php',
        'RealPosterum\\AiProcessing\\Service\\ResponseParser' => 'lib/Service/ResponseParser.php',
        'RealPosterum\\AiProcessing\\Table\\ProcessingLogTable' => 'lib/Table/ProcessingLogTable.php',
        'RealPosterum\\AiProcessing\\Table\\ProcessingTaskTable' => 'lib/Table/ProcessingTaskTable.php',
        'RealPosterum\\AiProcessing\\Ui\\AdminProductButton' => 'lib/Ui/AdminProductButton.php',
    ]
);
