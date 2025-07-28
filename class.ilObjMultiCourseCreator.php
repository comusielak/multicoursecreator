<?php

/**
 * Multi Course Creator Object
 */
class ilObjMultiCourseCreator extends ilObjectPlugin
{
    protected array $created_courses = [];
    protected array $base_settings = [];

    /**
     * Constructor
     */
    public function __construct(int $a_ref_id = 0)
    {
        parent::__construct($a_ref_id);
    }

    /**
     * Get type
     */
    final public function initType(): void
    {
        $this->setType("xmcc");
    }

    /**
     * Create object in database
     */
    protected function doCreate(bool $clone_mode = false): void
    {
        global $DIC;
        $db = $DIC->database();

        $id = $db->nextId('rep_robj_xmcc_data');
        $db->insert('rep_robj_xmcc_data', [
            'id' => ['integer', $id],
            'obj_id' => ['integer', $this->getId()],
            'created_courses' => ['text', json_encode([])],
            'base_settings' => ['text', json_encode([])],
            'created_at' => ['timestamp', date('Y-m-d H:i:s')]
        ]);
    }

    /**
     * Read object data from database
     */
    protected function doRead(): void
    {
        global $DIC;
        $db = $DIC->database();

        $query = 'SELECT * FROM rep_robj_xmcc_data WHERE obj_id = ' . $db->quote($this->getId(), 'integer');
        $result = $db->query($query);

        if ($row = $db->fetchAssoc($result)) {
            $this->created_courses = json_decode($row['created_courses'], true) ?? [];
            $this->base_settings = json_decode($row['base_settings'], true) ?? [];
        }
    }

    /**
     * Update object in database
     */
    protected function doUpdate(): void
    {
        global $DIC;
        $db = $DIC->database();

        $db->update('rep_robj_xmcc_data', [
            'created_courses' => ['text', json_encode($this->created_courses)],
            'base_settings' => ['text', json_encode($this->base_settings)],
        ], [
            'obj_id' => ['integer', $this->getId()]
        ]);
    }

    /**
     * Delete object from database
     */
    protected function doDelete(): void
    {
        global $DIC;
        $db = $DIC->database();

        $db->manipulate('DELETE FROM rep_robj_xmcc_data WHERE obj_id = ' . $db->quote($this->getId(), 'integer'));
    }

    /**
     * Clone object
     */
    protected function doCloneObject(ilObject2 $new_obj, int $a_target_id, ?int $a_copy_id = null): void
    {
        /** @var ilObjMultiCourseCreator $new_obj */
        $new_obj->setCreatedCourses([]);
        $new_obj->setBaseSettings($this->base_settings);
        $new_obj->update();
    }

    /**
     * Create multiple courses with same settings
     */
    public function createMultipleCourses(
        int $count,
        string $base_name,
        bool $add_numbering,
        string $numbering_format,
        int $target_ref_id,
        array $course_settings
    ): array {
        global $DIC;
        $tree = $DIC->repositoryTree();
        $access = $DIC->access();
        
        // Check permission to create courses in target folder
        if (!$access->checkAccess('create', '', $target_ref_id, 'crs')) {
            throw new ilException('No permission to create courses in target folder');
        }

        $created_course_ids = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $course_title = $base_name;
            if ($add_numbering) {
                $course_title .= str_replace('##', sprintf('%02d', $i), $numbering_format);
            }

            // Create new course object
            $course = new ilObjCourse();
            $course->setTitle($course_title);
            $course->setDescription('');
            $course->create();
            
            // Apply course settings
            $this->applyCourseSettings($course, $course_settings);
            
            // Create reference and add to tree
            $course->createReference();
            $course->putInTree($target_ref_id);
            $course->setPermissions($target_ref_id);
            
            // Add creator as admin
            $course->getMemberObject()->add($DIC->user()->getId(), ilCourseConstants::CRS_ADMIN);
            $course->getMemberObject()->updateNotification(
                $DIC->user()->getId(),
                (bool) $DIC->settings()->get('mail_crs_admin_notification', '1')
            );
            $course->getMemberObject()->updateContact($DIC->user()->getId(), 1);
            
            $course->update();
            
            $created_course_ids[] = [
                'obj_id' => $course->getId(),
                'ref_id' => $course->getRefId(),
                'title' => $course_title
            ];
        }

        // Store created courses in this object
        $this->created_courses = array_merge($this->created_courses, $created_course_ids);
        $this->base_settings = $course_settings;
        $this->update();

        return $created_course_ids;
    }

    /**
     * Apply settings to course
     */
    protected function applyCourseSettings(ilObjCourse $course, array $settings): void
    {
        // Online status
        if (isset($settings['online'])) {
            $property_online = $course->getObjectProperties()->getPropertyIsOnline();
            $online = $settings['online'] ? $property_online->withOnline() : $property_online->withOffline();
            $course->getObjectProperties()->storePropertyIsOnline($online);
        }

        // Availability period
        if (isset($settings['availability_start']) && isset($settings['availability_end'])) {
            if ($settings['availability_start'] && $settings['availability_end']) {
                $course->setActivationStart($settings['availability_start']);
                $course->setActivationEnd($settings['availability_end']);
                $course->setActivationVisibility(true);
            }
        }

        // Subscription settings
        if (isset($settings['subscription_type'])) {
            $course->setSubscriptionType((int) $settings['subscription_type']);
            
            // Subscription period
            if (isset($settings['subscription_start']) && isset($settings['subscription_end'])) {
                if ($settings['subscription_start'] && $settings['subscription_end']) {
                    $course->setSubscriptionLimitationType(ilCourseConstants::IL_CRS_SUBSCRIPTION_LIMITED);
                    $course->setSubscriptionStart($settings['subscription_start']);
                    $course->setSubscriptionEnd($settings['subscription_end']);
                } else {
                    $course->setSubscriptionLimitationType(ilCourseConstants::IL_CRS_SUBSCRIPTION_UNLIMITED);
                }
            }
        }

        // Max members
        if (isset($settings['max_members']) && $settings['max_members'] > 0) {
            $course->enableSubscriptionMembershipLimitation(true);
            $course->setSubscriptionMaxMembers((int) $settings['max_members']);
            
            // Enable waiting list if max members is set
            $course->enableWaitingList(true);
            $course->setWaitingListAutoFill(false);
        }

        // Subscription password
        if (isset($settings['subscription_password']) && !empty($settings['subscription_password'])) {
            $course->setSubscriptionPassword($settings['subscription_password']);
        }
    }

    // Getter and Setter methods
    public function getCreatedCourses(): array
    {
        return $this->created_courses;
    }

    public function setCreatedCourses(array $courses): void
    {
        $this->created_courses = $courses;
    }

    public function getBaseSettings(): array
    {
        return $this->base_settings;
    }

    public function setBaseSettings(array $settings): void
    {
        $this->base_settings = $settings;
    }
}