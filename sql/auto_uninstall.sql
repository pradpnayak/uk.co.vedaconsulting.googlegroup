-- drop custom value table
DROP TABLE IF EXISTS civicrm_value_googlegroup_settings;

-- drop custom set and their fields

DELETE cf.*
  FROM civicrm_custom_field cf
    INNER JOIN civicrm_custom_group cg on cf.custom_group_id = cg.id
  WHERE cg.name = 'Googlegroup_Settings';

DELETE FROM `civicrm_custom_group`
  WHERE table_name = 'civicrm_value_googlegroup_settings'
    AND name = 'Googlegroup_Settings';
