<?php

/**
 * Multi Course Creator plugin database update steps
 */
?>
<#1>
<?php
// Create main data table
if (!$ilDB->tableExists('rep_robj_xmcc_data')) {
    $fields = array(
        'id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
            'default' => 0
        ),
        'obj_id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
            'default' => 0
        ),
        'created_courses' => array(
            'type' => 'clob',
            'notnull' => false
        ),
        'base_settings' => array(
            'type' => 'clob',
            'notnull' => false
        ),
        'external_courses' => array(
            'type' => 'clob',
            'notnull' => false
        ),
        'created_at' => array(
            'type' => 'timestamp',
            'notnull' => true
        )
    );
    
    $ilDB->createTable('rep_robj_xmcc_data', $fields);
    $ilDB->addPrimaryKey('rep_robj_xmcc_data', array('id'));
    $ilDB->createSequence('rep_robj_xmcc_data');
    $ilDB->addIndex('rep_robj_xmcc_data', array('obj_id'), 'i1');
}
?>
<#2>
<?php
// Add external_courses column for existing installations
if ($ilDB->tableExists('rep_robj_xmcc_data')) {
    if (!$ilDB->tableColumnExists('rep_robj_xmcc_data', 'external_courses')) {
        $ilDB->addTableColumn('rep_robj_xmcc_data', 'external_courses', array(
            'type' => 'clob',
            'notnull' => false
        ));
    }
}
?>