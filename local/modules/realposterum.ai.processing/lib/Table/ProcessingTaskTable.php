<?php

namespace RealPosterum\AiProcessing\Table;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

class ProcessingTaskTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_realposterum_ai_task';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new IntegerField('PRODUCT_ID', ['required' => true]),
            new IntegerField('SOURCE_IBLOCK_ID', ['required' => true]),
            new StringField('STATUS', ['required' => true]),
            new TextField('PAYLOAD_JSON', ['required' => true]),
            new TextField('PROMPT_TEXT'),
            new TextField('RESULT_JSON'),
            new TextField('ERROR_MESSAGE'),
            new DatetimeField('CREATED_AT', ['required' => true]),
            new DatetimeField('UPDATED_AT', ['required' => true]),
            new DatetimeField('PROCESSED_AT'),
            new DatetimeField('DECIDED_AT'),
        ];
    }
}
