<?php
declare(strict_types=1);

namespace kooditorm\hyperctl;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [],
            'commands' => [
                StartServerCommand::class,
                StopServerCommand::class,
                RestartServerCommand::class,
                StatusServerCommand::class
            ],
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