<?php

return [
  'name' => 'DedupeRuleGroup',
  'table' => 'civicrm_dedupe_rule_group',
  'class' => 'CRM_Dedupe_DAO_DedupeRuleGroup',
  'getInfo' => fn() => [
    'title' => ts('Deduperulegroup'),
    'title_plural' => ts('Deduperulegroups'),
    'description' => ts('Dedupe rule groups'),
    'add' => '1.8',
  ],
  'getIndices' => fn() => [
    'UI_name' => [
      'fields' => [
        'name' => TRUE,
      ],
      'unique' => TRUE,
      'add' => '5.54',
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => ts('Rule Group ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => ts('Unique dedupe rule group id'),
      'add' => '1.8',
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'contact_type' => [
      'title' => ts('Contact Type'),
      'sql_type' => 'varchar(12)',
      'input_type' => 'Select',
      'description' => ts('The type of contacts this group applies to'),
      'add' => '1.8',
      'input_attrs' => [
        'maxlength' => 12,
      ],
      'pseudoconstant' => [
        'table' => 'civicrm_contact_type',
        'key_column' => 'name',
        'label_column' => 'label',
        'condition' => 'parent_id IS NULL',
      ],
    ],
    'threshold' => [
      'title' => ts('Threshold'),
      'sql_type' => 'int',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => ts('The weight threshold the sum of the rule weights has to cross to consider two contacts the same'),
      'add' => '1.8',
    ],
    'used' => [
      'title' => ts('Length'),
      'sql_type' => 'varchar(12)',
      'input_type' => 'Radio',
      'required' => TRUE,
      'description' => ts('Whether the rule should be used for cases where usage is Unsupervised, Supervised OR General(programatically)'),
      'add' => '4.3',
      'input_attrs' => [
        'maxlength' => 12,
      ],
      'pseudoconstant' => [
        'callback' => 'CRM_Core_SelectValues::getDedupeRuleTypes',
      ],
    ],
    'name' => [
      'title' => ts('Name'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'description' => ts('Unique name of rule group'),
      'add' => '2.1',
      'input_attrs' => [
        'maxlength' => 255,
      ],
    ],
    'title' => [
      'title' => ts('Title'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'description' => ts('Label of the rule group'),
      'add' => '4.1',
      'input_attrs' => [
        'maxlength' => 255,
      ],
    ],
    'is_reserved' => [
      'title' => ts('Reserved?'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'required' => TRUE,
      'description' => ts('Is this a reserved rule - a rule group that has been optimized and cannot be changed by the admin'),
      'add' => '4.1',
      'default' => FALSE,
    ],
  ],
];