<?php

namespace RealPosterum\AiProcessing\Service;

use Bitrix\Main\Config\Option;

class ModuleSettings
{
    public function __construct(private string $moduleId)
    {
    }

    public function getApiBaseUrl(): string
    {
        return Option::get($this->moduleId, 'api_base_url', 'https://api.openai.com/v1/chat/completions');
    }

    public function getApiToken(): string
    {
        return Option::get($this->moduleId, 'api_token', '');
    }

    public function getModel(): string
    {
        return Option::get($this->moduleId, 'model', 'gpt-4.1-mini');
    }

    public function getSystemPrompt(): string
    {
        return Option::get(
            $this->moduleId,
            'system_prompt',
            'Ты помогаешь улучшать карточки товаров в каталоге Bitrix и должен возвращать только JSON.'
        );
    }

    public function getPromptTemplate(): string
    {
        return Option::get(
            $this->moduleId,
            'default_prompt_template',
            "Проанализируй товар и верни JSON со структурой fields, properties, summary.\nТовар:\n#PRODUCT_JSON#"
        );
    }
}
