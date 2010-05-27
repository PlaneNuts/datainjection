<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2008 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */
class PluginDatainjectionInjectionType {

   const NO_VALUE = 'none';

   /**
    * Return all injectable types
    * @param only_primary return only primary types
    * @return an array which contains array(itemtype => itemtype name)
    */
   static function getItemtypes($only_primary=false) {
      global $INJECTABLE_TYPES;

     $values = array();
      foreach ($INJECTABLE_TYPES as $type => $plugin) {
         $injectionclass = new $type();
         if (!$only_primary || ($only_primary && $injectionclass->isPrimaryType())) {
            $typename = PluginDatainjectionInjectionType::getParentObjectName($type);
            $values[$typename] = call_user_func(array($type,'getTypeName'));
         }
      }
      asort($values);
      return $values;
   }

   /**
    * Display a list of all importable types using datainjection plugin
    * @param value the selected value
    * @return nothing
    */
   static function dropdown($value='',$only_primary=false) {
       return Dropdown::showFromArray('itemtype',
                                      self::getItemtypes($only_primary),
                                      array('value'=>$value));
   }

   /**
    * Get all types linked with a primary type
    * @param primary_type
    * @param value
    */
   static function dropdownLinkedTypes($mapping_or_info,$options=array()) {
      global $INJECTABLE_TYPES,$LANG,$CFG_GLPI;

      $p['primary_type']      = '';
      $p['itemtype']          = PluginDatainjectionInjectionType::NO_VALUE;
      $p['mapping_or_info']   = json_encode($mapping_or_info->fields);
      $p['called_by']         = get_class($mapping_or_info);
      foreach ($options as $key => $value) {
         $p[$key] = $value;
      }

      $mappings_id = $mapping_or_info->fields['id'];
      $values = array();

      if ($p['itemtype'] == PluginDatainjectionInjectionType::NO_VALUE
            && $mapping_or_info->fields['itemtype'] != PluginDatainjectionInjectionType::NO_VALUE) {
         $p['itemtype'] = $mapping_or_info->fields['itemtype'];
      }

      //Add null value
      $values[PluginDatainjectionInjectionType::NO_VALUE] = $LANG["datainjection"]["mapping"][6];

      //Add primary_type to the list of availables types
      $type = new $p['primary_type']();
      $values[$p['primary_type']] = $type->getTypeName();

      foreach ($INJECTABLE_TYPES as $type => $plugin) {
         $injectionClass = new $type();
         $connected_to = $injectionClass->connectedTo();
         if (in_array($p['primary_type'],$connected_to)) {
            $typename = getItemTypeForTable($injectionClass->getTable());
            $values[$typename] = call_user_func(array($type,'getTypeName'));
         }
      }
      asort($values);


      $rand = Dropdown::showFromArray("data[".$mapping_or_info->fields['id']."][itemtype]",
                                      $values,
                                      array('value'=>$p['itemtype']));

      $p['itemtype'] = '__VALUE__';

      $url = $CFG_GLPI["root_doc"]."/plugins/datainjection/ajax/dropdownChooseField.php";
      ajaxUpdateItemOnSelectEvent("dropdown_data[".$mapping_or_info->fields['id']."][itemtype]$rand",
                                  "span_field_".$mapping_or_info->fields['id'],
                                  $url,$p);
      ajaxUpdateItem("span_field_".$mapping_or_info->fields['id'],$url,$p,false,
                     "dropdown_data[".$mapping_or_info->fields['id']."][itemtype]$rand");
      return $rand;
   }

   static function getParentObjectName($injectionClass='') {
      return get_parent_class($injectionClass);
   }

   static function dropdownFields($options = array()) {
      global $LANG,$CFG_GLPI;

      $used = array();
      $p['itemtype'] = PluginDatainjectionInjectionType::NO_VALUE;
      $p['primary_type'] = '';
      $p['mapping_or_info'] = array();
      $p['called_by'] = '';
      foreach ($options as $key => $value) {
         $p[$key] = $value;
      }
      $mapping_or_info = json_decode(stripslashes_deep($options['mapping_or_info']),true);

      $fields = array();
      $fields[PluginDatainjectionInjectionType::NO_VALUE] = $LANG["datainjection"]["mapping"][7];

      //By default field has no default value
      $mapping_value = PluginDatainjectionInjectionType::NO_VALUE;

      if ($p['itemtype'] != PluginDatainjectionInjectionType::NO_VALUE) {

         //If a value is still present for this mapping
         if($mapping_or_info['value'] != PluginDatainjectionInjectionType::NO_VALUE) {
            $mapping_value = $mapping_or_info['value'];
         }

         $injectionClass = PluginDatainjectionCommonInjectionLib::getInjectionClassInstance($p['itemtype']);

         foreach ($injectionClass->getOptions() as $option) {
            //If it's a real option (not a group label) and if field is not blacklisted
            //and if a linkfield is defined (meaning that the field can be updated)
            if (is_array($option)
                  && $option['injectable'] == PluginDatainjectionCommonInjectionLib::FIELD_INJECTABLE) {
               $fields[$option['linkfield']] = $option['name'];
               if ($mapping_value == PluginDatainjectionInjectionType::NO_VALUE
                     && $p['called_by'] == 'PluginDatainjectionMapping'
                        && PluginDatainjectionInjectionType::isEqual($option,$mapping_or_info)) {
                  $mapping_value = $option['linkfield'];
               }
            }
         }
         $used = self::getUsedMappingsOrInfos($p);
      }
      asort($fields);


      $rand = Dropdown::showFromArray("data[".$mapping_or_info['id']."][value]",
                                      $fields,
                                      array('value'=>$mapping_value,'used'=>$used));
     $url = $CFG_GLPI["root_doc"]."/plugins/datainjection/ajax/dropdownMandatory.php";
      ajaxUpdateItemOnSelectEvent("dropdown_data[".$mapping_or_info['id']."][value]$rand",
                                  "span_mandatory_".$mapping_or_info['id'],
                                  $url,$p);
      ajaxUpdateItem("span_mandatory_".$mapping_or_info['id'],$url,$p,false,
                     "dropdown_data[".$mapping_or_info['id']."][value]$rand");
   }

   /**
    * Incidates if the name given corresponds to the current searchOption
    * @param option the current searchOption (field definition)
    * @param mapping
    * @return boolean the value matches the searchOption or not
    */
   static function isEqual($option = array(), $mapping) {
      global $LANG;
      $name = strtolower($mapping['name']);
      if (self::testBasicEqual(strtolower($mapping['name']), $option)) {
         return true;
      }
      else {
         //Manage mappings begining with N° or n°
         $new_name = preg_replace("/[n|N]°/",$LANG['financial'][4],$name);
         if (self::testBasicEqual(strtolower($new_name), $option)) {
            return true;
         }
         else {
           //Field may match is it was in plural...
           $plural_name = $name.'s';
           if (self::testBasicEqual(strtolower($plural_name), $option)) {
              return true;
           }
           return false;
         }
      }
   }

   static function testBasicEqual($name, $option = array()) {
            //Basic tests
      if ( strtolower($option['field']) == $name
            || strtolower($option['name']) == $name
               || strtolower($option['linkfield']) == $name) {
               return true;
            }
      else {
         return false;
      }
   }

   static function showMandatoryCheckbox($options = array()) {
      $mapping_or_info = json_decode(stripslashes_deep($options['mapping_or_info']),true);

      //TODO : to improve
      $checked = '';
      if ($mapping_or_info['is_mandatory']) {
         $checked = 'checked';
      }
      if ($options['called_by'] == 'PluginDatainjectionInfo'
            || ($options['primary_type'] == $options['itemtype'])) {
         echo "<input type='checkbox' ".
               "name='data[".$mapping_or_info['id']."][is_mandatory]' $checked>";
      }
   }


   static function getUsedMappingsOrInfos($options = array()) {
      global $DB;

      $p['itemtype'] = PluginDatainjectionInjectionType::NO_VALUE;
      $p['primary_type'] = '';
      $p['mapping_or_info'] = array();
      $p['called_by'] = '';
      foreach ($options as $key => $value) {
         $p[$key] = $value;
      }
      $mapping_or_info = json_decode(stripslashes_deep($options['mapping_or_info']),true);

      $used = array();
      $table = (($p['called_by']=='PluginDatainjectionMapping')
                  ?"glpi_plugin_datainjection_mappings"
                     :"glpi_plugin_datainjection_infos");

      $datas = getAllDatasFromTable($table,"`models_id`='".$mapping_or_info['models_id']."'");

      $injectionClass = PluginDatainjectionCommonInjectionLib::getInjectionClassInstance($p['itemtype']);
      $options = $injectionClass->getOptions();
      foreach ($datas as $data) {
         if ($data['value'] != PluginDatainjectionInjectionType::NO_VALUE) {
            foreach ($options as $option) {
               if ($option['linkfield'] == $data['value']
                        && $option['displaytype'] != 'multiline_text'
                           && $mapping_or_info['value'] != $data['value']) {
                  $used[$option['linkfield']] = $option['linkfield'];
                  break;
               }
            }
         }
      }

      return $used;
   }

}
?>