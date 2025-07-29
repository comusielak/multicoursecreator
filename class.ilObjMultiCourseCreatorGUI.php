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
                
            case 'addExternalCourse':
                $this->checkPermission('write');
                $this->addExternalCourse();
                break;
                
            case 'removeExternalCourse':
                $this->checkPermission('write');
                $this->removeExternalCourse();
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
                $this->plugin->txt("rep_robj_xmcc_create_courses"),
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
        $table_html .= '<th>' . $this->plugin->txt('rep_robj_xmcc_actions') . '</th>';
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
                          $this->plugin->txt('rep_robj_xmcc_goto_course') . '</a></td>';
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
        
        // DEBUG: Check object type
        error_log("DEBUG: manageCourses() - object type: " . get_class($this->object));
        error_log("DEBUG: manageCourses() - object id: " . $this->object->getId());
        error_log("DEBUG: manageCourses() - ref_id: " . $this->object->getRefId());

        // Get all courses (created + external)
        $all_courses = $this->getAllManagedCourses();
        
        if (empty($all_courses)) {
            $this->tpl->setOnScreenMessage('info', $this->plugin->txt('rep_robj_xmcc_no_courses_yet'));
            
            // Show form to add external courses
            $this->showAddExternalCourseForm();
            return;
        }
        
        // Show add external course form first
        $add_form_html = $this->getAddExternalCourseFormHTML();
        
        // Then show course management form
        $manage_form = $this->initManageCoursesForm($all_courses);
        
        // Combine both forms
        $combined_html = $add_form_html . "<hr style='margin: 20px 0;'>" . $manage_form->getHTML();
        $this->tpl->setContent($combined_html);
    }

    /**
     * Get all managed courses (created + external)
     */
    protected function getAllManagedCourses(): array
    {
        $created_courses = $this->object->getCreatedCourses();
        $external_courses = $this->object->getExternalCourses();
        
        // Merge and return all courses
        return array_merge($created_courses, $external_courses);
    }

    /**
     * Show form to add external courses
     */
    protected function showAddExternalCourseForm(): void
    {
        $html = $this->getAddExternalCourseFormHTML();
        $this->tpl->setContent($html);
    }

    /**
     * Get HTML for add external course form
     */
    protected function getAddExternalCourseFormHTML(): string
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle("Externe Kurse hinzufügen");
        
        // Section header
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle("Bestehende Kurse zur Verwaltung hinzufügen");
        $form->addItem($section);
        
        // Ref-ID input
        $ref_id_input = new ilTextInputGUI("Kurs Ref-ID", "external_ref_id");
        $ref_id_input->setInfo("Geben Sie die Referenz-ID eines bestehenden Kurses ein, um ihn zur Verwaltung hinzuzufügen.");
        $ref_id_input->setSize(10);
        $ref_id_input->setMaxLength(10);
        $form->addItem($ref_id_input);
        
        // Multiple ref-ids
        $multiple_input = new ilTextAreaInputGUI("Mehrere Ref-IDs", "multiple_ref_ids");
        $multiple_input->setInfo("Alternativ: Mehrere Ref-IDs durch Kommas getrennt (z.B. 12345, 12346, 12347)");
        $multiple_input->setRows(3);
        $multiple_input->setCols(50);
        $form->addItem($multiple_input);
        
        $form->addCommandButton("addExternalCourse", "Kurs(e) hinzufügen");
        
        // Show existing external courses
        $external_courses = $this->object->getExternalCourses();
        if (!empty($external_courses)) {
            $ext_section = new ilFormSectionHeaderGUI();
            $ext_section->setTitle("Bereits hinzugefügte externe Kurse");
            $form->addItem($ext_section);
            
            $course_list = "";
            foreach ($external_courses as $course) {
                if (ilObject::_exists($course['ref_id'], true)) {
                    $this->ctrl->setParameter($this, 'course_ref_id', $course['ref_id']);
                    $remove_link = $this->ctrl->getLinkTarget($this, 'removeExternalCourse');
                    $this->ctrl->setParameter($this, 'course_ref_id', ''); // Parameter zurücksetzen
                    $course_list .= "<div style='margin: 5px 0;'>";
                    $course_list .= "<strong>" . htmlspecialchars($course['title']) . "</strong> (Ref-ID: " . $course['ref_id'] . ") ";
                    $course_list .= "<a href='" . $remove_link . "' style='color: red; margin-left: 10px;'>[Entfernen]</a>";
                    $course_list .= "</div>";
                }
            }
            
            $info_item = new ilCustomInputGUI("", "");
            $info_item->setHtml($course_list);
            $form->addItem($info_item);
        }
        
        return $form->getHTML();
    }

    /**
     * Add external course to management
     */
    protected function addExternalCourse(): void
    {
        global $DIC;
        
        $single_ref_id = 0;
        if (isset($_POST['external_ref_id']) && is_numeric($_POST['external_ref_id'])) {
            $single_ref_id = (int) $_POST['external_ref_id'];
        }
        
        $multiple_ref_ids = "";
        if (isset($_POST['multiple_ref_ids'])) {
            $multiple_ref_ids = trim((string) $_POST['multiple_ref_ids']);
        }
        
        $ref_ids = [];
        
        // Single ref_id
        if ($single_ref_id > 0) {
            $ref_ids[] = $single_ref_id;
        }
        
        // Multiple ref_ids
        if (!empty($multiple_ref_ids)) {
            $multiple_ids = explode(',', $multiple_ref_ids);
            foreach ($multiple_ids as $id) {
                $id = (int) trim($id);
                if ($id > 0) {
                    $ref_ids[] = $id;
                }
            }
        }
        
        if (empty($ref_ids)) {
            $this->tpl->setOnScreenMessage('failure', 'Keine gültigen Ref-IDs eingegeben.');
            $this->manageCourses();
            return;
        }
        
        $added_count = 0;
        $errors = [];
        $external_courses = $this->object->getExternalCourses();
        
        foreach ($ref_ids as $ref_id) {
            try {
                // Check if object exists and is a course
                if (!ilObject::_exists($ref_id, true)) {
                    $errors[] = "Ref-ID $ref_id: Objekt existiert nicht";
                    continue;
                }
                
                $obj_id = ilObject::_lookupObjId($ref_id);
                $type = ilObject::_lookupType($obj_id);
                
                if ($type !== 'crs') {
                    $errors[] = "Ref-ID $ref_id: Ist kein Kurs (Type: $type)";
                    continue;
                }
                
                // Check if already managed
                $already_exists = false;
                foreach ($external_courses as $existing) {
                    if ($existing['ref_id'] == $ref_id) {
                        $already_exists = true;
                        break;
                    }
                }
                
                if ($already_exists) {
                    $errors[] = "Ref-ID $ref_id: Bereits zur Verwaltung hinzugefügt";
                    continue;
                }
                
                // Add to external courses
                $course_title = ilObject::_lookupTitle($obj_id);
                $external_courses[] = [
                    'ref_id' => $ref_id,
                    'obj_id' => $obj_id,
                    'title' => $course_title,
                    'added_date' => date('Y-m-d H:i:s')
                ];
                
                $added_count++;
                
            } catch (Exception $e) {
                $errors[] = "Ref-ID $ref_id: " . $e->getMessage();
            }
        }
        
        // Save external courses
        $this->object->setExternalCourses($external_courses);
        $this->object->update();
        
        if ($added_count > 0) {
            $this->tpl->setOnScreenMessage('success', "$added_count Kurs(e) zur Verwaltung hinzugefügt.", true);
        }
        
        if (!empty($errors)) {
            $this->tpl->setOnScreenMessage('info', implode('<br>', $errors), true);
        }
        
        $this->ctrl->redirect($this, 'manageCourses');
    }

    /**
     * Remove external course from management
     */
protected function removeExternalCourse(): void
{
    $ref_id = 0;
    if (isset($_GET['course_ref_id']) && is_numeric($_GET['course_ref_id'])) {
        $ref_id = (int) $_GET['course_ref_id'];
    }
    
    if ($ref_id <= 0) {
        $this->tpl->setOnScreenMessage('failure', 'Ungültige Ref-ID.');
        $this->manageCourses();
        return;
    }
    
    $external_courses = $this->object->getExternalCourses();
    $updated_courses = [];
    $found = false;
    
    foreach ($external_courses as $course) {
        if ($course['ref_id'] != $ref_id) {
            $updated_courses[] = $course;
        } else {
            $found = true;
        }
    }
    
    if ($found) {
        $this->object->setExternalCourses($updated_courses);
        $this->object->update();
        $this->tpl->setOnScreenMessage('success', 'Kurs aus der Verwaltung entfernt.', true);
    } else {
        $this->tpl->setOnScreenMessage('failure', 'Kurs nicht gefunden.', true);
    }
    
    $this->ctrl->redirect($this, 'manageCourses');
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
        
        // "Alle auswählen" Checkbox
        $select_all = new ilCheckboxInputGUI("Alle Kurse auswählen", "select_all_courses");
        $select_all->setInfo("Klicken Sie hier, um alle Kurse auf einmal zu markieren/entmarkieren");
        $form->addItem($select_all);
        
        // JavaScript für "Alle auswählen" Funktionalität
        $js_code = "
        <script type='text/javascript'>
        document.addEventListener('DOMContentLoaded', function() {
            // Finde die 'Alle auswählen' Checkbox
            var selectAllCheckbox = document.querySelector('input[name=\"select_all_courses\"]');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    // Finde alle Kurs-Checkboxen
                    var courseCheckboxes = document.querySelectorAll('input[name=\"selected_courses[]\"]');
                    courseCheckboxes.forEach(function(checkbox) {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                });
            }
            
            // Optional: Wenn alle Kurse manuell ausgewählt sind, aktiviere auch 'Alle auswählen'
            var courseCheckboxes = document.querySelectorAll('input[name=\"selected_courses[]\"]');
            courseCheckboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    var allChecked = true;
                    courseCheckboxes.forEach(function(cb) {
                        if (!cb.checked) allChecked = false;
                    });
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = allChecked;
                    }
                });
            });
        });
        </script>";
        
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
        
        // JavaScript ans Ende des Formulars anhängen
        global $DIC;
        $DIC->ui()->mainTemplate()->addOnLoadCode("
            var selectAllCheckbox = document.querySelector('input[name=\"select_all_courses\"]');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    var courseCheckboxes = document.querySelectorAll('input[name=\"selected_courses[]\"]');
                    courseCheckboxes.forEach(function(checkbox) {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                });
            }
            
            var courseCheckboxes = document.querySelectorAll('input[name=\"selected_courses[]\"]');
            courseCheckboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    var allChecked = true;
                    var anyChecked = false;
                    courseCheckboxes.forEach(function(cb) {
                        if (cb.checked) anyChecked = true;
                        if (!cb.checked) allChecked = false;
                    });
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = allChecked;
                    }
                });
            });
        ");
        
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
                $this->plugin->txt("rep_robj_xmcc_create_courses"),
                $this->ctrl->getLinkTarget($this, "editProperties")
            );
        }

        // Manage Courses tab - hier war es vorher nicht!
        $this->tabs_gui->addTab(
            "manage_courses",
            "Kurse verwalten", 
            $this->ctrl->getLinkTarget($this, "manageCourses")
        );           
        
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