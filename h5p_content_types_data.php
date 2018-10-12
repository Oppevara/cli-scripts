<?php
/**
* This CLI script would get count for all existing H5P nodes and write result to a file.
* This CLI script would extract data for each unique H5P content type used in the system and write results to a file.
* The the difference from DB query is that it checks H5P.Column and adds encountered
* types data to the results.
* Running: php h5p_content_types_data.php
*/

if ( !php_sapi_name() === 'cli')
{
  die('CLI mode required!' . PHP_EOL);
}

require __DIR__ . '/config.php';

$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, DB_PORT);


if ( $db->connect_error )
{
  die('Database Connection Error (' . $db->connect_errno . ') ' . $db->connection_error . PHP_EOL);
}

$db->set_charset('utf8');

/**
 * Adds a row to libraries data output if this type has not yet been stored.
 * @param array  $data                Libraries data (passed by reference)
 * @param string $library             Library name and version
 * @param string $jsonContent         JSON encoded data
 * @param string $filteredJsonContent Filtered JSON encoded data (not really used as of the moment; unavailable for the embedded content types as that data is based on either filterd or unfiltered initial data)
 */
function add_to_data(&$data, $library, $jsonContent, $filteredJsonContent)
{
  // Preventive measure to identify unsuitable values, this generally means issues with data or handling code logic
  if ( empty($library) )
  {
    throw new Exception('Empty library definition! Please debug and check the data for the issues!');
  }

  if ( !array_key_exists( $library, $data ) )
  {
    $data[$library] = json_decode($jsonContent);
  }
}

/**
 * Determines if content has embedded data that is of relevance.
 * @param  string  $machineName MachineName of the content (no version info included)
 * @return boolean
 */
function has_embedded_data($machineName)
{
  return in_array($machineName, ['H5P.Column', 'H5P.CoursePresentation', 'H5P.DragQuestion', 'H5P.QuestionSet', 'H5P.Accordion', 'H5P.ImageHotspots', 'H5P.DocumentationTool', 'H5P.Agamotto', 'H5P.InteractiveVideo', 'H5P.Questionnaire', 'H5P.StandardPage',]);
}

/**
 * Extract MachineName from library definition.
 * @param  string $library Library definition with version number included
 * @return string          MachineName for the library
 */
function library_to_machine_name($library)
{
  $split = explode(' ', $library, 2);

  return $split[0];
}

/**
 * Debugging helper. Runs print_r() to dump data if object does not have 'library' key.
 * @param  object $object   H5P embedded object
 * @param  mixed  $dumpable Either array of object with base data
 * @return void
 */
function dd_library($object, $dumpable)
{
  if (empty($object->library))
  {
    print_r($dumpable);die();
  }
}

/**
 * Recursively handles embedded data within the H5P content.
 * @param  array  $data        Final array with types data (reference)
 * @param  object $decoded     H5P object data
 * @param  string $machineName MachineName of the type being handled
 * @return void
 */
function handle_embedded(&$data, $decoded, $machineName)
{
  if ( has_embedded_data($machineName) )
  {

    switch($machineName) {
      case 'H5P.Column':
      // 'content' (array of objects) >>> object ('params' holds data, 'library' holds library name with version)
      foreach ( $decoded->content as $single )
      {
        // This one does seem to also hold additional objects that are not H5P content
        if ( isset($single->content->library) )
        {
          add_to_data($data, $single->content->library, json_encode($single->content->params), '');
          handle_embedded($data, $single->content->params, library_to_machine_name($single->content->library));
        }
      }
      break;
      case 'H5P.CoursePresentation':
      //'presentation' >>> 'slides' (array of objects) >>> 'elements' (array of objects) >>> object ('action' >>> 'params' holds data, 'library' holds library name and version)
      foreach ($decoded->presentation->slides as $slide )
      {
        if ( isset($slide->elements) )
        {
          foreach( $slide->elements as $element )
          {
            if (isset($element->action->library))
            {
              add_to_data($data, $element->action->library, json_encode($element->action->params), '');
              handle_embedded($data, $element->action->params, library_to_machine_name($element->action->library));
            }
          }
        }
      }
      break;
      case 'H5P.DragQuestion':
      // 'question' (object) >>> 'task' (object) >>> 'elements' (array of objects) >>> 'type' (object) >>> 'params' olds data, 'library' holds library name and version
      foreach ( $decoded->question->task->elements as $single)
      {
        add_to_data($data, $single->type->library, json_encode($single->type->params), '');
        handle_embedded($data, $single->type->params, library_to_machine_name($single->type->library));
      }
      break;
      case 'H5P.QuestionSet':
      // 'questions' (array of objects) >>> 'params' holds data, 'library' holds library name and version
      foreach ( $decoded->questions as $single )
      {
        if (isset($single->library))
        {
          add_to_data($data, $single->library, json_encode($single->params), '');
          handle_embedded($data, $single->params, library_to_machine_name($single->library));
        }
      }
      break;
      case 'H5P.Accordion':
      // 'panels' (array of objects) >>> 'content' (object) >>> 'params' holds data, 'library' holds library name and version
      if ( isset($decoded->panels) )
      {
        foreach ( $decoded->panels as $single )
        {
          add_to_data($data, $single->content->library, json_encode($single->content->params), '');
          handle_embedded($data, $single->content->params, library_to_machine_name($single->content->library));
        }
      }
      break;
      case 'H5P.ImageHotspots':
      // 'hotspots' (array of objects) >>> 'content' (array of objects) >>> 'params' holds data, 'library' holds library name and version
      foreach ( $decoded->hotspots as $single )
      {
        foreach( $single->content as $subsingle)
        {
          if (isset($subsingle->library))
          {
            add_to_data($data, $subsingle->library, json_encode($subsingle->params), '');
            handle_embedded($data, $subsingle->params, library_to_machine_name($subsingle->library));
          }
        }
      }
      break;
      case 'H5P.DocumentationTool':
      // 'pagesList' (array of objects) >>> 'params' holds data, 'library' holds library name and version; 'H5P.StandardPage' seems to have a 'helpTextLabel' that is translatable and it also embeddable types in 'elementList' (array of objects) that could also have translations; 'H5P.TextInputField' does have 'remainingChars'
      foreach ( $decoded->pagesList as $single )
      {
        if (isset($single->library))
        {
          add_to_data($data, $single->library, json_encode($single->params), '');
          handle_embedded($data, $single->params, library_to_machine_name($single->library));
        }
      }
      break;
      case 'H5P.Agamotto':
      // 'items' (array of objects) seems to hold only H5P.Image elements that do not seem to have any translatable texts
      foreach ( $decoded->items as $single )
      {
        add_to_data($data, $single->image->library, json_encode($single->image->params), '');
        handle_embedded($data, $single->image->params, library_to_machine_name($single->image->library));
      }
      break;
      case 'H5P.InteractiveVideo':
      // 'interactiveVideo' (object) >>> 'assets' (object) >>> interactions (array of objects) >>> 'action' (object) >>> 'params' holds data, 'library' holds library name and version
      if ( isset($decoded->interactiveVideo->assets->interactions) )
      {
        foreach ( $decoded->interactiveVideo->assets->interactions as $single)
        {
          add_to_data($data, $single->action->library, json_encode($single->action->params), '');
          handle_embedded($data, $single->action->params, library_to_machine_name($single->action->library));
        }
      }
      add_to_data($data, $decoded->interactiveVideo->summary->task->library, json_encode($decoded->interactiveVideo->summary->task->params), '');
      handle_embedded($data, $decoded->interactiveVideo->summary->task->params, library_to_machine_name($decoded->interactiveVideo->summary->task->library));
      break;
      case 'H5P.Questionnaire':
      // 'questionnaireElements' (array ob objects) >>> library (object) >>> 'params' holds data, 'library' holds library name and version"
      foreach ( $decoded->questionnaireElements as $single )
      {
        add_to_data($data, $single->library->library, json_encode($single->library->params), '');
        handle_embedded($data, $single->library->params, library_to_machine_name($single->library->library));
      }
      break;
      case 'H5P.StandardPage':
      // 'H5P.StandardPage' seems to have a 'helpTextLabel' that is translatable and it also embeddable types in 'elementList' (array of objects) that could also have translations
      foreach ( $decoded->elementList as $single )
      {
        if (isset($single->library))
        {
          add_to_data($data, $single->library, json_encode($single->params), '');
          handle_embedded($data, $single->params, library_to_machine_name($single->library));
        }
      }
      break;
      default:
      throw new Exception('Unhandlable type: ' . $machineName);
      break;
    }
  }
}

$query = 'SELECT node.nid AS nid, h5p_nodes.content_id AS content_id, h5p_libraries.machine_name AS machine_name, CONCAT(h5p_libraries.machine_name, " ", h5p_libraries.major_version, ".", h5p_libraries.minor_version) AS library, h5p_nodes.json_content AS json_content, h5p_nodes.filtered AS filtered_json_content'
. ' FROM node'
. ' INNER JOIN h5p_nodes ON h5p_nodes.content_id = node.vid'
. ' INNER JOIN h5p_libraries ON h5p_libraries.library_id = h5p_nodes.main_library_id'
. ' WHERE node.type = \'h5p_content\'';

$data = [];

if ( $result = $db->query($query) )
{
  while( $row = $result->fetch_object() )
  {
    add_to_data($data, $row->library, $row->json_content, $row->filtered_json_content);

    $decoded = json_decode($row->json_content);

    if ( is_object( $decoded ) ) {
      handle_embedded($data, $decoded, $row->machine_name);
    }
    else
    {
      echo $row->json_content . PHP_EOL;
      die('Could not parse JSON' . PHP_EOL);
    }
  }

  $result->close();
}
else
{
  echo $db->error . PHP_EOL;
}

$db->close();

$handle = fopen(__DIR__ . '/results/h5p_content_types_data.json', 'w+');
fwrite($handle, json_encode($data));
fclose($handle);
echo 'Result written to results/h5p_content_types_data.json' . PHP_EOL;
