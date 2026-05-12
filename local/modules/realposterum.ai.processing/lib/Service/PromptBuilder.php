<?php

namespace RealPosterum\AiProcessing\Service;

use RealPosterum\AiProcessing\Model\ProductSnapshot;

class PromptBuilder
{
    public function __construct(private ModuleSettings $settings)
    {
    }

    public function build(ProductSnapshot $snapshot): string
    {
        return str_replace(
            '#PRODUCT_JSON#',
            json_encode($snapshot->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $this->settings->getPromptTemplate()
        );
    }
}
