<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Table;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class ProcessingLogTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_realposterum_ai_log';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new IntegerField('TASK_ID'),
            new StringField('LOG_TYPE', ['required' => true]),
            new StringField('LEVEL', ['required' => true]),
            new TextField('MESSAGE'),
            new TextField('CONTEXT_JSON'),
            new DatetimeField('CREATED_AT', ['required' => true]),
        ];
    }
}
