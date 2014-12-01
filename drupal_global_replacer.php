<?php

/*  

******* DRUPAL GLOBAL REPLACER ******* 

To be called as a drush script.

Syntax with included file:
drush scr /path/to/drupal_global_replacer.php --execute --file=/path/to/file.php

Note that file.php should contain an array named $patterns with search strings as keys and replacement strings as values.

Syntax with single find-replace pair:
drush scr /path/to/drupal_global_replacer.php --execute search-string replacement-string

*/



if (drush_get_option('file')) {
  $file = drush_get_option('file');
  if (file_exists($file)) {
    include($file);
  } else {
    drush_print("Unable to locate " . $file);
    exit();
  }
  if (!is_array($patterns)) {
    drush_print("Your included file does not contain a \$patterns array.");
    exit();
  }
} else {
  if (!isset($args[1]) || !isset($args[2])) {
    drush_print("You must specify a search string and replacement string");
    exit();
  } else {
    $patterns = array($args[1] => $args[2]);
  }
}

$execute = (strlen(drush_get_option('execute'))) ? TRUE : FALSE;
$debug = (strlen(drush_get_option('debug'))) ? TRUE : FALSE;

foreach ($patterns AS $search => $replace) {
  _check_relative_links($search, $replace, $execute);
}


function _check_relative_links($search, $replace, $execute = FALSE) {

  // Preliminary list of field_types to check
  $field_types = array('text_long','link_field','text');

  // Find all the fields of those types
  $query = db_select('field_config', 'c');
  $query->join('field_config_instance', 'i', 'c.id = i.field_id');
  $query->distinct()
        ->fields('c', array('field_name', 'type'))
        ->fields('i', array('data'))
        ->condition('c.type', $field_types, 'IN');
  $results = $query->execute()->fetchAll();
  
  foreach ($results AS $field) {
    $data = unserialize($field->data);
    $fields[] = array(
      'name' => $field->field_name,
      'type' => $field->type,
      'label' => $data['label'],
    );
  }
  
  
  // Append body to the results as it's not in field_config.
  $fields[] = array('name' => 'body', 'type' => 'long_text', 'label' => 'Body');
  

  // Initialize our row array
  $rows = array(array('Field label','Node type', 'Edit link', 'Last updated', 'Node title'));
  
  // Loop over each field and query it for links.
  foreach ($fields AS $field) {
    // Build our query components
    $table = 'field_data_' . $field['name'];
    $append = ($field['type'] == 'link_field') ? '_url' : '_value';
    $column = $field['name'] . $append;
    
    $query = db_select($table, 'f');
    $query->join('node', 'n', 'f.revision_id = n.vid');
    $query->distinct()
      ->fields('n', array('nid','title', 'changed', 'type'))
      ->addExpression("'" . $field['label'] . "'", 'field_label');
    $query->condition(
        db_or()
          // Build out a big OR clause of LIKE conditions 
          ->condition($column, '%' . $search . '%', 'LIKE')
      );
    // Build out a rows array to use within theme_table.
    foreach ($query->execute()->fetchAll() AS $row) {
      $rows[] = array($row->field_label, $row->type, url('node/' . $row->nid . '/edit'),  date('F d, Y',$row->changed), $row->title);
    }
    
    if ($execute) {
      $table = 'field_data_' . $field['name'];
      $append = ($field['type'] == 'link_field') ? '_url' : '_value';
      $column = $field['name'] . $append;
      $query = db_update($table)
        ->expression($column, 'REPLACE(' . $column . ', :search, :replace)', array(
          ':search' => $search,
          ':replace' => $replace,
        ));
        
      if ($debug) {
        $querystring = str_replace(array(':search',':replace'), array("'" . $search . "'", "'" . $replace . "'"), $query->__toString());
        drush_print($querystring);
      }
      $query->execute();
    }
  }
  drush_print_table($rows, TRUE);
}
