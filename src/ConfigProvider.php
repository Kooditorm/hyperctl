<?php
declare(strict_types=1);

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [],
            'commands' => [],
            'listeners' => [],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__
                    ]
                ]
            ],
            'publish' => []
        ];
    }
}