<?php
/**
* Gets node identifiers that include certain type, also looks inside H5P.Column
* Running: php h5p_nodes_including_type.php TYPE_MACHONE_NAME
* Has optional arguments for file type: csv or json (defaults to csv)
* Example: php h5p_nodes_including_type.php H5P.QuestionSet csv
*/

if ( !php_sapi_name() === 'cli')
{
  die('CLI mode required!' . PHP_EOL);
}

require __DIR__ . '/config.php';

if (!isset($argv[1])) {
  echo 'H5P content type machine name not provided!' . PHP_EOL;
  exit;
}

$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, DB_PORT);


if ( $db->connect_error )
{
  die('Database Connection Error (' . $db->connect_errno . ') ' . $db->connection_error . PHP_EOL);
}

$db->set_charset('utf8');

$query = 'SELECT node.nid AS nid, h5p_nodes.content_id AS content_id, h5p_libraries.machine_name AS machine_name, h5p_nodes.json_content AS json_content, h5p_libraries.major_version AS major_version, h5p_libraries.minor_version AS minor_version'
. ' FROM node'
. ' INNER JOIN h5p_nodes ON h5p_nodes.content_id = node.vid'
. ' INNER JOIN h5p_libraries ON h5p_libraries.library_id = h5p_nodes.main_library_id'
. ' WHERE node.type = \'h5p_content\'';

$data = [];

if ( $result = $db->query($query) )
{
  echo 'Number of nodes: ' . $result->num_rows . PHP_EOL;

  while( $row = $result->fetch_object() )
  {
    if ( $row->machine_name === 'H5P.Column' )
    {
      $decoded = json_decode($row->json_content);

      if ( is_object( $decoded ) )
      {
        if ( isset($decoded->content) )
        {
          foreach ( $decoded->content as $single )
          {
            if ( isset($single->content->library) )
            {
              $machineName = strstr($single->content->library, ' ', true);

              if ( $machineName === $argv[1] )
              {
                $data[] = [$row->nid, $single->content->library];
              }
            }
          }
        }
      }
      else
      {
        echo $row->json_content . PHP_EOL;
        die('Could not parse JSON' . PHP_EOL);
      }
    } else if ( $row->machine_name === $argv[1] ) {
      $data[] = [$row->nid, $row->machine_name . ' ' .$row->major_version . '.' . $row->minor_version];
    }
  }

  $result->close();
}
else
{
  echo $db->error . PHP_EOL;
}


$db->close();

$outputType = 'csv';

if ( $argv && sizeof($argv) >= 3 )
{
  $outputType = $argv[2];
}

if ( $outputType === 'json' )
{
  $handle = fopen(__DIR__ . '/results/h5p_nodes_including_type.json', 'w+');
  fwrite($handle, json_encode($data));
  fclose($handle);
  echo 'Result written to results/h5p_nodes_including_type.json' . PHP_EOL;
}
else if ( $outputType === 'csv')
{
  $handle = fopen(__DIR__ . '/results/h5p_nodes_including_type.csv', 'w+');
  fputcsv($handle, [ 'nid', 'type',]);
  foreach ( $data as $row )
  {
    fputcsv($handle, $row);
  }
  fclose($handle);
  echo 'Result written to results/h5p_nodes_including_type.csv' . PHP_EOL;
}
else
{
  die('Unsupported Output Type: ' . $outputType . PHP_EOL);
}
