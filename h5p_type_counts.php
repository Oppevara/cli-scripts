<?php
/**
 * This CLI script would get count for all existing H5P nodes and write result to a file.
 * The the difference from DB query is that it checks H5P.Column and adds encountered
 * types to the counts.
 * Running: php h5p_type_counts.php
 * Has optional arguments for file type: csv or json (defaults to csv)
 */

if ( !php_sapi_name() === 'cli')
{
    die('CLI mode required!' . PHP_EOL);
}

require __DIR__ . '/config.php';

function add_to_data(&$data, $machineName)
{
    if ( array_key_exists( $machineName, $data ) )
    {
        $data[$machineName]['count'] += 1;
    }
    else
    {
        $data[$machineName] = [
            'machine_name' => $machineName,
            'count' => 1,
        ];
    }
}

$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, DB_PORT);


if ( $db->connect_error )
{
    die('Database Connection Error (' . $db->connect_errno . ') ' . $db->connection_error . PHP_EOL);
}

$db->set_charset('utf8');

$query = 'SELECT node.nid AS nid, h5p_nodes.content_id AS content_id, h5p_libraries.machine_name AS machine_name, h5p_nodes.json_content AS json_content'
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
        add_to_data($data, $row->machine_name);

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

                            if ( $machineName )
                            {
                                add_to_data($data, $machineName);
                            }
                            else
                            {
                                die('Bad Machine Name: ' . $machineName . PHP_EOL);
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

if ( $argv && sizeof($argv) >= 2 )
{
    $outputType = $argv[1];
}

if ( $outputType === 'json' )
{
    $handle = fopen(__DIR__ . '/results/h5p_type_counts.json', 'w+');
    fwrite($handle, json_encode($data));
    fclose($handle);
    echo 'Result written to results/h5p_type_counts.json' . PHP_EOL;
}
else if ( $outputType === 'csv')
{
    $handle = fopen(__DIR__ . '/results/h5p_type_counts.csv', 'w+');
    fputcsv($handle, [ 'machine_name', 'count',]);
    foreach ( $data as $row )
    {
        fputcsv($handle, $row);
    }
    fclose($handle);
    echo 'Result written to results/h5p_type_counts.csv' . PHP_EOL;
}
else
{
    die('Unsupported Output Type: ' . $outputType . PHP_EOL);
}
