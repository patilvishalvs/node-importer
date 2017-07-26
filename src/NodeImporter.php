<?php

namespace Drupal\node_importer;

use \Drupal\node\Entity\Node;
use \Drupal\taxonomy\Entity\Term;
use \Drupal\file\Entity\File;

class NodeImporter {

  public static function createNode($data, $fields_mapper, $node_bundle, &$context) {
    // Create node object with attached file.
    if (!empty($data)) {
      $values = ['type' => $node_bundle];
      foreach ($fields_mapper as $key => $field) {
        $field_settings = explode('|', $field['field']);
        $field_name = $field_settings[0];
        $target_type = isset($field_settings[1]) ? $field_settings[1] : null;
        $target_bundle = isset($field_settings[2]) ? $field_settings[2] : null;
        $settings_count = count($field_settings);
        $separator = (!empty($field['separator'])) ? $field['separator'] : null;
        if (!is_null($separator)) {
          $data[$key] = array_map('trim', explode($separator, $data[$key]));
        }
        else {
          $data[$key] = [$data[$key]];
        }
        switch ($settings_count) {
          case 1:
            $values[$field_name] = $data[$key];
            break;
          case 2:
            if ($target_type == 'file') {
              foreach ($data[$key] as $path) {
                $file_content = file_get_contents($path);
                $file = file_save_data($file_content, 'public://file-' . REQUEST_TIME . '.png', FILE_EXISTS_REPLACE);
                $values[$field_name][] = $file->id();
              }
            }
            break;
          case 3:
            $values[$field_name] = NodeImporter::getTargetId($target_type, $target_bundle, $data[$key]);
            break;
        }
      }
      $node = Node::create($values);
      $node->save();
      $context['message'] = 'creating node...';
      $context['results'] ++;
    }
  }

  public static function createNodeFinishedCallback($success, $results, $operations) {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
          count($results), 'One post processed.', '@count posts processed.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);
  }

  private static function getTargetId($target_type, $target_bundle, $values) {
    $etids = [];
    foreach ($values as $value) {
      $properties = [];
      $info = NodeImporter::getTargetInfo($target_type);
      $properties[$info[0]] = $value;
      $properties[$info[1]] = $target_bundle;
      $entity = \Drupal::entityManager()->getStorage($target_type)->loadByProperties($properties);
      $entity = reset($entity);
      if (empty($entity) && $target_type == 'taxonomy_term' && $target_bundle == 'tags') {
        $entity = Term::create([
              'name' => $value,
              'vid' => $target_bundle,
        ]);
        $entity->save();
      }
      $etids[] = !empty($entity) ? $entity->id() : 0;
    }
    return $etids;
  }

  private static function getTargetInfo($entity_type) {
    $info = [
      'taxonomy_term' => [
        'name',
        'vid'
      ],
      'node' => [
        'title',
        'bundle'
      ],
    ];
    return $info[$entity_type];
  }

}
