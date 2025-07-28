<?php

// Required includes for ILIAS 9 forms
require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
require_once 'Services/Form/classes/class.ilNumberInputGUI.php';
require_once 'Services/Form/classes/class.ilTextInputGUI.php';
require_once 'Services/Form/classes/class.ilCheckboxInputGUI.php';
require_once 'Services/Form/classes/class.ilRadioGroupInputGUI.php';
require_once 'Services/Form/classes/class.ilRadioOption.php';
require_once 'Services/Form/classes/class.ilDateDurationInputGUI.php';
require_once 'Services/Form/classes/class.ilFormSectionHeaderGUI.php';
// Note: Repository Selector might not be needed or has different path in ILIAS 9

/**
 * Multi Course Creator GUI
 * 
 * @ilCtrl_isCalledBy ilObjMultiCourseCreatorGUI: ilRepositoryGUI, ilObjPluginDispatchGUI, ilAdministrationGUI
 * @ilCtrl_Calls ilObjMultiCourseCreatorGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI
 */
class ilObjMultiCourseCreatorGUI extends ilObjectPluginGUI
{
    /**
     * Get type
     */
    final public function getType(): string
    {
        return "xmcc";
    }

    /**
     * Handles all commmands of this class, centralizes permission checks
     */
    public function performCommand(string $cmd): void
    {
        // Load plugin language file
        $this->plugin->loadLanguageModule();
        
        switch ($cmd) {
            case 'editProperties':
                $this->checkPermission('write');
                $this->editProperties();
                break;
                
            case 'updateProperties':
                $this->checkPermission('write');
                $this->updateProperties();
                break;
                
            case 'showContent':
            default:
                $this->checkPermission('read');
                $this->showContent();
                break;
        }
    }

    /**
     * After object has been created -> jump to this command
     */
    public function getAfterCreationCmd(): string
    {
        return "editProperties";
    }

    /**
     * Get standard command
     */
    public function getStandardCmd(): string
    {
        return "showContent";
    }

    /**
     * Show content (main view)
     */
    protected function showContent(): void
    {
        global $DIC;
        
        $this->tabs_gui->activateTab("content");
        
        $toolbar = $DIC->toolbar();
        
        // Add create courses button if user has write permission
        if ($this->access->checkAccess('write', '', $this->object->getRefId())) {
            $toolbar->addButton(
                $this->lng->txt("rep_robj_xmcc_create_courses"),
                $this->ctrl->getLinkTarget($this, "editProperties")
            );
        }
        
        // Show created courses
        $created_courses = $this->object->getCreatedCourses();
        
        if (empty($created_courses)) {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('rep_robj_xmcc_no_courses_yet'));
        } else {
            $this->showCreatedCoursesList($created_courses);
        }
    }

    /**
     * Show list of created courses
     */
    protected function showCreatedCoursesList(array $courses): void
    {
        global $DIC;
        
        $table_html = '<div class="table-responsive">';
        $table_html .= '<table class="table table-striped">';
        $table_html .= '<thead><tr>';
        $table_html .= '<th>' . $this->lng->txt('title') . '</th>';
        $table_html .= '<th>' . $this->lng->txt('rep_robj_xmcc_actions') . '</th>';
        $table_html .= '</tr></thead><tbody>';
        
        foreach ($courses as $course_data) {
            $ref_id = $course_data['ref_id'];
            $title = $course_data['title'];
            
            // Check if course still exists
            if (!ilObject::_exists($ref_id, true)) {
                continue;
            }
            
            $link = ilLink::_getStaticLink($ref_id, 'crs');
            
            $table_html .= '<tr>';
            $table_html .= '<td><a href="' . $link . '">' . htmlspecialchars($title) . '</a></td>';
            $table_html .= '<td><a href="' . $link . '" class="btn btn-default btn-sm">' . 
                          $this->lng->txt('rep_robj_xmcc_goto_course') . '</a></td>';
            $table_html .= '</tr>';
        }
        
        $table_html .= '</tbody></table></div>';
        
        $this->tpl->setContent($table_html);
    }

    /**
     * Edit properties (course creation form)
     */
    protected function editProperties(): void
    {
        $this->tabs_gui->activateTab("properties");
        
        $form = $this->initPropertiesForm();
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Initialize properties form
     */
    protected function initPropertiesForm(): ilPropertyFormGUI
    {
        global $DIC;
        
        // Load plugin language
        $this->plugin->loadLanguageModule();
        
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->plugin->txt("rep_robj_xmcc_create_courses"));
        
        // Course count
        $count = new ilNumberInputGUI($this->plugin->txt("rep_robj_xmcc_course_count"), "course_count");
        $count->setRequired(true);
        $count->setMinValue(1);
        $count->setMaxValue(50);
        $count->setValue(5);
        $count->setSize(3);
        $form->addItem($count);
        
        // Base name
        $name = new ilTextInputGUI($this->plugin->txt("rep_robj_xmcc_base_name"), "base_name");
        $name->setRequired(true);
        $name->setMaxLength(128);
        $name->setValue("Kurs");
        $form->addItem($name);
        
        // Numbering
        $numbering = new ilCheckboxInputGUI($this->plugin->txt("rep_robj_xmcc_add_numbering"), "add_numbering");
        $numbering->setChecked(true);
        
        $format = new ilTextInputGUI($this->plugin->txt("rep_robj_xmcc_numbering_format"), "numbering_format");
        $format->setInfo($this->plugin->txt("rep_robj_xmcc_numbering_format_info"));
        $format->setValue(" - ##");
        $format->setMaxLength(20);
        $numbering->addSubItem($format);
        
        $form->addItem($numbering);
        
        // Target folder - use simple text input with ref_id for now
        $target = new ilTextInputGUI($this->plugin->txt("rep_robj_xmcc_target_folder"), "target_ref_id");
        $target->setRequired(true);
        $target->setInfo($this->plugin->txt("rep_robj_xmcc_target_folder_info"));
        $target->setSize(10);
        $target->setMaxLength(10);
        // Set current folder as default
        $target->setValue((string) $this->object->getRefId());
        $form->addItem($target);
        
        // Course settings section
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->plugin->txt("rep_robj_xmcc_course_settings"));
        $form->addItem($section);
        
        // Online status
        $online = new ilCheckboxInputGUI($this->plugin->txt("rep_robj_xmcc_online"), "online");
        $online->setChecked(true);
        $online->setInfo($this->plugin->txt("rep_robj_xmcc_online_info"));
        $form->addItem($online);
        
        // Availability period
        $avail_period = new ilDateDurationInputGUI($this->plugin->txt("rep_robj_xmcc_availability"), "availability_period");
        $avail_period->setShowTime(true);
        $avail_period->setInfo($this->plugin->txt("rep_robj_xmcc_availability_info"));
        $form->addItem($avail_period);
        
        // Subscription type
        $sub_type = new ilRadioGroupInputGUI($this->plugin->txt("rep_robj_xmcc_subscription_type"), "subscription_type");
        $sub_type->setRequired(true);
        $sub_type->setValue((string) ilCourseConstants::IL_CRS_SUBSCRIPTION_DIRECT);
        
        $direct = new ilRadioOption(
            $this->plugin->txt("rep_robj_xmcc_subscription_direct"),
            (string) ilCourseConstants::IL_CRS_SUBSCRIPTION_DIRECT
        );
        $sub_type->addOption($direct);
        
        $password = new ilRadioOption(
            $this->plugin->txt("rep_robj_xmcc_subscription_password"),
            (string) ilCourseConstants::IL_CRS_SUBSCRIPTION_PASSWORD
        );
        $pass_field = new ilTextInputGUI($this->lng->txt("password"), "subscription_password");
        $pass_field->setMaxLength(32);
        $password->addSubItem($pass_field);
        $sub_type->addOption($password);
        
        $confirmation = new ilRadioOption(
            $this->plugin->txt("rep_robj_xmcc_subscription_confirmation"),
            (string) ilCourseConstants::IL_CRS_SUBSCRIPTION_CONFIRMATION
        );
        $sub_type->addOption($confirmation);
        
        $form->addItem($sub_type);
        
        // Subscription period
        $sub_period = new ilDateDurationInputGUI($this->plugin->txt("rep_robj_xmcc_subscription_period"), "subscription_period");
        $sub_period->setShowTime(true);
        $sub_period->setInfo($this->plugin->txt("rep_robj_xmcc_subscription_period_info"));
        $form->addItem($sub_period);
        
        // Max members
        $max_members = new ilNumberInputGUI($this->plugin->txt("rep_robj_xmcc_max_members"), "max_members");
        $max_members->setMinValue(0);
        $max_members->setMaxValue(9999);
        $max_members->setInfo($this->plugin->txt("rep_robj_xmcc_max_members_info"));
        $form->addItem($max_members);
        
        $form->addCommandButton("updateProperties", $this->plugin->txt("rep_robj_xmcc_create_courses"));
        $form->addCommandButton("showContent", $this->lng->txt("cancel"));
        
        return $form;
    }

    /**
     * Update properties (create courses)
     */
    protected function updateProperties(): void
    {
        $form = $this->initPropertiesForm();
        
        if ($form->checkInput()) {
            try {
                // Get form values
                $count = (int) $form->getInput('course_count');
                $base_name = $form->getInput('base_name');
                $add_numbering = (bool) $form->getInput('add_numbering');
                $numbering_format = $form->getInput('numbering_format') ?: ' ##';
                $target_ref_id = (int) $form->getInput('target_ref_id');
                
                // Course settings
                $course_settings = [
                    'online' => (bool) $form->getInput('online'),
                    'subscription_type' => (int) $form->getInput('subscription_type'),
                    'subscription_password' => $form->getInput('subscription_password'),
                    'max_members' => (int) $form->getInput('max_members')
                ];
                
                // Availability period
                $avail_period = $form->getItemByPostVar('availability_period');
                if ($avail_period->getStart() && $avail_period->getEnd()) {
                    $course_settings['availability_start'] = $avail_period->getStart()->get(IL_CAL_UNIX);
                    $course_settings['availability_end'] = $avail_period->getEnd()->get(IL_CAL_UNIX);
                }
                
                // Subscription period
                $sub_period = $form->getItemByPostVar('subscription_period');
                if ($sub_period->getStart() && $sub_period->getEnd()) {
                    $course_settings['subscription_start'] = $sub_period->getStart()->get(IL_CAL_UNIX);
                    $course_settings['subscription_end'] = $sub_period->getEnd()->get(IL_CAL_UNIX);
                }
                
                // Create courses
                $created_courses = $this->object->createMultipleCourses(
                    $count,
                    $base_name,
                    $add_numbering,
                    $numbering_format,
                    $target_ref_id,
                    $course_settings
                );
                
                $this->tpl->setOnScreenMessage('success', sprintf(
                    $this->lng->txt('rep_robj_xmcc_courses_created'),
                    count($created_courses)
                ), true);
                
                $this->ctrl->redirect($this, 'showContent');
                
            } catch (Exception $e) {
                $this->tpl->setOnScreenMessage('failure', $e->getMessage());
                $form->setValuesByPost();
                $this->tpl->setContent($form->getHTML());
            }
        } else {
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
        }
    }

    /**
     * Set tabs
     */
    protected function setTabs(): void
    {
        global $DIC;
        
        // Content tab
        $this->tabs_gui->addTab(
            "content",
            $this->lng->txt("content"),
            $this->ctrl->getLinkTarget($this, "showContent")
        );
        
        // Properties tab (only for write access)
        if ($this->access->checkAccess('write', '', $this->object->getRefId())) {
            $this->tabs_gui->addTab(
                "properties",
                $this->lng->txt("rep_robj_xmcc_create_courses"),
                $this->ctrl->getLinkTarget($this, "editProperties")
            );
        }
        
        // Info tab
        if ($this->access->checkAccess('visible', '', $this->object->getRefId())) {
            $this->tabs_gui->addTab(
                "info_short",
                $this->lng->txt("info_short"),
                $this->ctrl->getLinkTargetByClass("ilinfoscreengui", "showSummary")
            );
        }
        
        // Permissions tab
        if ($this->access->checkAccess('edit_permission', '', $this->object->getRefId())) {
            $this->tabs_gui->addTab(
                "perm_settings",
                $this->lng->txt("perm_settings"),
                $this->ctrl->getLinkTargetByClass("ilpermissiongui", "perm")
            );
        }
    }
}