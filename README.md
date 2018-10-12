# Õppevara CLI scripts

## Requirements

* Machine with PHP version `7.x` installed (could also work with older ones)
* Having required extensions: `mysqli`, `json` (might also require others; it should be safe to assume that ones required by Drupal would be sufficient)
* Running from Command Line Interface

## Usage

* Fill the `config.php` with database connection data
* Run any script from CLI
  * Example: `php h5p_type_counts.php json`
  * Example: `php -d memory_limit=512M h5p_content_types_data.php`
    * This one sets the memory limit to the process (this one requires a lot as it potentially handles huge data types)
