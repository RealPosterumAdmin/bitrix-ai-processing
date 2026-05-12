<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Table;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class ProcessingTaskTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_realposterum_ai_task';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new IntegerField('PRODUCT_ID', ['required' => true]),
            new IntegerField('SOURCE_IBLOCK_ID', ['required' => true]),
            new StringField('STATUS', ['required' => true]),
            new StringField('IDEMPOTENCY_KEY', ['required' => true]),
            new StringField('REQUEST_METHOD'),
            new StringField('ENDPOINT'),
            new TextField('REQUEST_HEADERS_JSON'),
            new TextField('SOURCE_SNAPSHOT_JSON', ['required' => true]),
            new TextField('MAPPED_PAYLOAD_JSON', ['required' => true]),
            new TextField('REQUEST_BODY'),
            new TextField('RESPONSE_BODY'),
            new TextField('PARSED_DATA_JSON'),
            new TextField('SELECTED_FIELDS_JSON'),
            new TextField('ERROR_MESSAGE'),
            new DatetimeField('CREATED_AT', ['required' => true]),
            new DatetimeField('UPDATED_AT', ['required' => true]),
            new DatetimeField('LAST_ATTEMPT_AT'),
            new DatetimeField('RESPONSE_RECEIVED_AT'),
            new DatetimeField('APPLIED_AT'),
            new DatetimeField('REJECTED_AT'),
        ];
    }
}
