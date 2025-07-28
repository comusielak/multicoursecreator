<?php

/**
 * Multi Course Creator Plugin for ILIAS 9
 */
class ilMultiCourseCreatorPlugin extends ilRepositoryObjectPlugin
{
    const PLUGIN_ID = "xmcc";
    const PLUGIN_NAME = "MultiCourseCreator";

    /**
     * Get plugin name
     */
    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }

    /**
     * Load language module
     */
    public function loadLanguageModule(): void
    {
        global $DIC;
        $lng = $DIC->language();
        $lng->loadLanguageModule($this->getPrefix());
    }

    /**
     * Get object type
     */
    protected function uninstallCustom(): void
    {
        // Drop custom tables
        if ($this->db->tableExists('rep_robj_xmcc_data')) {
            $this->db->dropTable('rep_robj_xmcc_data');
        }
    }
}