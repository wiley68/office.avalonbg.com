<?php

namespace App\Ai;

use App\Ai\Tools\ExportNotesToXlsxTool;
use App\Ai\Tools\ManageCitiTool;
use App\Ai\Tools\ManageContactsTool;
use App\Ai\Tools\ManageDlazhnostiTool;
use App\Ai\Tools\ManageNotesTool;
use App\Ai\Tools\SendAgentResponseEmailTool;
use Laravel\Ai\Contracts\Tool;

/**
 * Регистър на tools за оркестратор и специализирани агенти.
 * Добавяйте нови tool класове тук при нови офис модули.
 */
final class OfficeAgentTools
{
    /**
     * Всички инструменти, с които оркестраторът може да делегира към подсистеми.
     *
     * @return list<Tool>
     */
    public static function forOrchestrator(): array
    {
        return [
            app(ManageNotesTool::class),
            app(ManageContactsTool::class),
            app(ManageCitiTool::class),
            app(ManageDlazhnostiTool::class),
            app(ExportNotesToXlsxTool::class),
            app(SendAgentResponseEmailTool::class),
            // Бъдещи: app(InvoiceTool::class), …
        ];
    }

    /**
     * Само инструменти за модул „бележки“.
     *
     * @return list<Tool>
     */
    public static function forNotes(): array
    {
        return [
            app(ManageNotesTool::class),
            app(ExportNotesToXlsxTool::class),
            app(SendAgentResponseEmailTool::class),
        ];
    }

    /**
     * Само инструменти за модул „контакти“.
     *
     * @return list<Tool>
     */
    public static function forContacts(): array
    {
        return [
            app(ManageContactsTool::class),
            app(ManageCitiTool::class),
            app(ManageDlazhnostiTool::class),
            app(SendAgentResponseEmailTool::class),
        ];
    }
}
