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
        
        // Debug: Log the ref_id being accessed
        global $DIC;
        $ref_id = $this->object ? $this->object->getRefId() : 'NO_OBJECT';
        $obj_id = $this->object ? $this->object->getId() : 'NO_OBJECT';
        error_log("DEBUG: performCommand() called with cmd='$cmd', ref_id='$ref_id', obj_id='$obj_id'");
        
        // Additional safety check
        if (!$this->object || !$this->object->getId()) {
            error_log("ERROR: No valid object found in performCommand()");
            $this->tpl->setOnScreenMessage('failure', 'Invalid plugin object. Please create a new one.');
            return;
        }
        
        switch ($cmd) {
            case 'editProperties':
                $this->checkPermission('write');
                $this->editProperties();
                break;
                
            case 'updateProperties':
                $this->checkPermission('write');
                $this->updateProperties();
                break;
                
            case 'manageCourses':
                $this->checkPermission('write');
                $this->manageCourses();
                break;
                
            case 'updateCourses':
                $this->checkPermission('write');
                $this->updateCourses();
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
     * Manage existing courses
     */
    protected function manageCourses(): void
    {
        $this->tabs_gui->activateTab("manage_courses");
        
        $created_courses = $this->object->getCreatedCourses();
        
        if (empty($created_courses)) {
            $this->tpl->setOnScreenMessage('info', $this->plugin->txt('rep_robj_xmcc_no_courses_yet'));
            return;
        }
        
        // Create form for batch updates
        $form = $this->initManageCoursesForm($created_courses);
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Initialize manage courses form
     */
    protected function initManageCoursesForm(array $courses): ilPropertyFormGUI
    {
        global $DIC;
        
        // Load plugin language
        $this->plugin->loadLanguageModule();
        
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->plugin->txt("rep_robj_xmcc_manage_courses"));
        
        // Course selection
        $course_section = new ilFormSectionHeaderGUI();
        $course_section->setTitle($this->plugin->txt("rep_robj_xmcc_select_courses"));
        $form->addItem($course_section);
        
        // Create checkboxes for each course
        $course_checkboxes = new ilCheckboxGroupInputGUI($this->plugin->txt("rep_robj_xmcc_courses"), "selected_courses");
        
        foreach ($courses as $course_data) {
            $ref_id = $course_data['ref_id'];
            $title = $course_data['title'];
            
            // Check if course still exists
            if (!ilObject::_exists($ref_id, true)) {
                continue;
            }
            
            // Load course to get current settings
            try {
                $course = new ilObjCourse($ref_id);
                $is_online = !$course->getOfflineStatus();
                $max_members = $course->getSubscriptionMaxMembers();
                $status_info = $is_online ? 
                    $this->plugin->txt("rep_robj_xmcc_status_online") : 
                    $this->plugin->txt("rep_robj_xmcc_status_offline");
                
                if ($max_members > 0) {
                    $status_info .= " | " . $this->plugin->txt("rep_robj_xmcc_max_members") . ": " . $max_members;
                }
                
                $option = new ilCheckboxOption($title . " (" . $status_info . ")", $ref_id);
                $course_checkboxes->addOption($option);
                
            } catch (Exception $e) {
                // Course might be deleted, skip it
                continue;
            }
        }
        
        $form->addItem($course_checkboxes);
        
        // Batch update settings
        $update_section = new ilFormSectionHeaderGUI();
        $update_section->setTitle($this->plugin->txt("rep_robj_xmcc_batch_settings"));
        $form->addItem($update_section);
        
        // Online/Offline status
        $online_update = new ilCheckboxInputGUI($this->plugin->txt("rep_robj_xmcc_update_online"), "update_online");
        
        $online_status = new ilRadioGroupInputGUI($this->plugin->txt("rep_robj_xmcc_online_status"), "online_status");
        $online_status->addOption(new ilRadioOption($this->plugin->txt("rep_robj_xmcc_set_online"), "1"));
        $online_status->addOption(new ilRadioOption($this->plugin->txt("rep_robj_xmcc_set_offline"), "0"));
        $online_status->setValue("1");
        $online_update->addSubItem($online_status);
        
        $form->addItem($online_update);
        
        // Max members update
        $members_update = new ilCheckboxInputGUI($this->plugin->txt("rep_robj_xmcc_update_max_members"), "update_max_members");
        
        $max_members = new ilNumberInputGUI($this->plugin->txt("rep_robj_xmcc_new_max_members"), "new_max_members");
        $max_members->setMinValue(0);
        $max_members->setMaxValue(9999);
        $max_members->setInfo($this->plugin->txt("rep_robj_xmcc_max_members_info"));
        $members_update->addSubItem($max_members);
        
        $form->addItem($members_update);
        
        // Subscription type update
        $sub_update = new ilCheckboxInputGUI($this->plugin->txt("rep_robj_xmcc_update_subscription"), "update_subscription");
        
        $sub_type = new ilRadioGroupInputGUI($this->plugin->txt("rep_robj_xmcc_subscription_type"), "new_subscription_type");
        $sub_type->addOption(new ilRadioOption(
            $this->plugin->txt("rep_robj_xmcc_subscription_direct"),
            (string) ilCourseConstants::IL_CRS_SUBSCRIPTION_DIRECT
        ));
        $sub_type->addOption(new ilRadioOption(
            $this->plugin->txt("rep_robj_xmcc_subscription_confirmation"),
            (string) ilCourseConstants::IL_CRS_SUBSCRIPTION_CONFIRMATION
        ));
        $sub_type->setValue((string) ilCourseConstants::IL_CRS_SUBSCRIPTION_DIRECT);
        $sub_update->addSubItem($sub_type);
        
        $form->addItem($sub_update);
        
        // Availability period update
        $avail_update = new ilCheckboxInputGUI($this->plugin->txt("rep_robj_xmcc_update_availability"), "update_availability");
        
        $avail_period = new ilDateDurationInputGUI($this->plugin->txt("rep_robj_xmcc_new_availability"), "new_availability_period");
        $avail_period->setShowTime(true);
        $avail_update->addSubItem($avail_period);
        
        $form->addItem($avail_update);
        
        $form->addCommandButton("updateCourses", $this->plugin->txt("rep_robj_xmcc_update_selected_courses"));
        $form->addCommandButton("manageCourses", $this->lng->txt("cancel"));
        
        return $form;
    }

    /**
     * Update selected courses with batch settings
     */
    protected function updateCourses(): void
    {
        $form = $this->initManageCoursesForm($this->object->getCreatedCourses());
        
        if ($form->checkInput()) {
            $selected_courses = $form->getInput('selected_courses') ?? [];
            
            if (empty($selected_courses)) {
                $this->tpl->setOnScreenMessage('failure', $this->plugin->txt('rep_robj_xmcc_no_courses_selected'));
                $this->manageCourses();
                return;
            }
            
            $updated_count = 0;
            $errors = [];
            
            foreach ($selected_courses as $ref_id) {
                try {
                    if (!ilObject::_exists($ref_id, true)) {
                        continue;
                    }
                    
                    $course = new ilObjCourse($ref_id);
                    
                    // Update online status
                    if ($form->getInput('update_online')) {
                        $online = (bool) $form->getInput('online_status');
                        $property_online = $course->getObjectProperties()->getPropertyIsOnline();
                        $online_prop = $online ? $property_online->withOnline() : $property_online->withOffline();
                        $course->getObjectProperties()->storePropertyIsOnline($online_prop);
                    }
                    
                    // Update max members
                    if ($form->getInput('update_max_members')) {
                        $max_members = (int) $form->getInput('new_max_members');
                        if ($max_members > 0) {
                            $course->enableSubscriptionMembershipLimitation(true);
                            $course->setSubscriptionMaxMembers($max_members);
                            $course->enableWaitingList(true);
                        } else {
                            $course->enableSubscriptionMembershipLimitation(false);
                            $course->enableWaitingList(false);
                        }
                    }
                    
                    // Update subscription type
                    if ($form->getInput('update_subscription')) {
                        $sub_type = (int) $form->getInput('new_subscription_type');
                        $course->setSubscriptionType($sub_type);
                    }
                    
                    // Update availability period
                    if ($form->getInput('update_availability')) {
                        $avail_period = $form->getItemByPostVar('new_availability_period');
                        if ($avail_period->getStart() && $avail_period->getEnd()) {
                            $course->setActivationStart($avail_period->getStart()->get(IL_CAL_UNIX));
                            $course->setActivationEnd($avail_period->getEnd()->get(IL_CAL_UNIX));
                            $course->setActivationVisibility(true);
                        } else {
                            $course->setActivationStart(0);
                            $course->setActivationEnd(0);
                            $course->setActivationVisibility(false);
                        }
                    }
                    
                    $course->update();
                    $updated_count++;
                    
                } catch (Exception $e) {
                    $errors[] = "Course " . $ref_id . ": " . $e->getMessage();
                }
            }
            
            if ($updated_count > 0) {
                $message = sprintf(
                    $this->plugin->txt('rep_robj_xmcc_courses_updated'),
                    $updated_count
                );
                $this->tpl->setOnScreenMessage('success', $message, true);
            }
            
            if (!empty($errors)) {
                $this->tpl->setOnScreenMessage('info', implode('<br>', $errors), true);
            }
            
            $this->ctrl->redirect($this, 'manageCourses');
            
        } else {
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
        }
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

        // Manage Courses tab - hier war es vorher nicht!
        $this->tabs_gui->addTab(
            "manage_courses",
            "Kurse verwalten", 
            $this->ctrl->getLinkTarget($this, "manageCourses")
        );        
    }
}