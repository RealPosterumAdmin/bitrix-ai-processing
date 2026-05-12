<?php

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
        'RealPosterum\\AiProcessing\\Repository\\ProcessingTaskRepository' => 'lib/Repository/ProcessingTaskRepository.php',
        'RealPosterum\\AiProcessing\\Service\\DecisionService' => 'lib/Service/DecisionService.php',
        'RealPosterum\\AiProcessing\\Service\\ModuleSettings' => 'lib/Service/ModuleSettings.php',
        'RealPosterum\\AiProcessing\\Service\\ProcessingService' => 'lib/Service/ProcessingService.php',
        'RealPosterum\\AiProcessing\\Service\\ProductSnapshotBuilder' => 'lib/Service/ProductSnapshotBuilder.php',
        'RealPosterum\\AiProcessing\\Service\\PromptBuilder' => 'lib/Service/PromptBuilder.php',
        'RealPosterum\\AiProcessing\\Table\\ProcessingTaskTable' => 'lib/Table/ProcessingTaskTable.php',
    ]
);
