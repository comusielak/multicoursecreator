<?php

/**
 * ListGUI implementation for Multi Course Creator
 */
class ilObjMultiCourseCreatorListGUI extends ilObjectPluginListGUI
{
    /**
     * Init type
     */
    public function initType(): void
    {
        $this->setType("xmcc");
    }

    /**
     * Get type
     */
    function getType(): string
    {
        return "xmcc";
    }

    /**
     * Get GUI class name
     */
    public function getGuiClass(): string
    {
        return "ilObjMultiCourseCreatorGUI";
    }

    /**
     * Get commands
     */
    public function initCommands(): array
    {
        return [
            [
                "permission" => "read",
                "cmd" => "showContent",
                "default" => true
            ],
            [
                "permission" => "write", 
                "cmd" => "editProperties",
                "txt" => $this->lng->txt("rep_robj_xmcc_create_courses"),
                "default" => false
            ]
        ];
    }

    /**
     * Get item properties
     */
    public function getProperties(): array
    {
        global $DIC;
        
        $props = [];
        
        // Show number of created courses
        try {
            // WICHTIG: obj_id verwenden, nicht ref_id!
            error_log("DEBUG: ListGUI - obj_id: " . $this->obj_id . ", ref_id: " . $this->ref_id);
            $obj = new ilObjMultiCourseCreator($this->ref_id); // Das ist korrekt!
            $created_courses = $obj->getCreatedCourses();
            
            if (!empty($created_courses)) {
                $props[] = [
                    "alert" => false,
                    "property" => $this->lng->txt("rep_robj_xmcc_created_courses"),
                    "value" => count($created_courses)
                ];
            }
        } catch (Exception $e) {
            error_log("ERROR in ListGUI getProperties: " . $e->getMessage());
            // Ignore errors when reading object
        }
        
        return $props;
    }
}