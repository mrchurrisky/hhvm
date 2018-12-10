<?hh

/**
 * Add variables to a WDDX packet with the specified ID
 *
 * @param resource $packet_id - packet_id   A WDDX packet, returned by
 *   wddx_packet_start().
 * @param mixed $var_name - var_name   Can be either a string naming a
 *   variable or an array containing strings naming the variables or
 *   another array, etc.
 * @param mixed $... - ...
 *
 * @return bool -
 */
<<__Native("ActRec", "ReadsCallerFrame")>>
function wddx_add_vars(resource $packet_id, ...): bool;

/**
 * Unserializes a WDDX packet
 *
 * @param string $packet - packet   A WDDX packet, as a string or stream.
 *
 * @return mixed - Returns the deserialized value which can be a string,
 *   a number or an array. Note that structures are deserialized into
 *   associative arrays.
 */

function wddx_deserialize($packet) : mixed {
  if (is_resource($packet)) {
    $packet = strval(stream_get_contents($packet));
  } elseif (!is_string($packet)) {
    trigger_error("wddx_deserialize(): " .
      "Expecting parameter 1 to be a string or a stream", E_WARNING);
    return null;
  }

  if ( $packet === '' ) {
    return null;
  }

  $xml = simplexml_load_string($packet);
  if (!$xml) {
    return null;
  }
  $root = $xml->xpath("(/wddxPacket[@version='1.0'] |
                        /wddxpacket[@version='1.0'] )/data");
  if (!count($root)) {
    return null;
  }
  return _wddx_deserialize_recursive($root[0]);
}

/**
 * Private function to walk the WDDX DOM tree
 */
function _wddx_deserialize_recursive($node) {
  $type = $node->getName();

  //variables
  switch ($type) {
    case "string":
      return (string) $node;
    case "number":
      $node = (string) $node;
      if ((int) $node == $node) {
        return (int) $node;
      }
      return (float) $node;
    case "boolean":
      if (!empty($node["value"])) {
        return (((string) $node["value"]) === 'true');
      }
      break;
    case "binary":
      return "binary data";
    case "dateTime":
      $dateTime = new DateTime((string) $node);
      return $dateTime->getTimestamp();
  }

  //setup for containers
  $array = array();
  $subchildren = $node->children();

  if ($type == "data") {
    if (count($subchildren)) {
      return _wddx_deserialize_recursive($subchildren[0]);
    } else {
      return null;
    }
  }

  //array
  if ($type == "array" ) {
    foreach ($subchildren as $subchild) {
        array_push(&$array, _wddx_deserialize_recursive($subchild));
    }
    return $array;
  }

  //struct
  if ($type == "struct" || $type == "recordset") {
    $isObject = false;
    $isFirst = true;
    if (count($subchildren)>0) {
      $firstchild = $subchildren[0];
      if ($firstchild["name"] == "php_class_name") {
        $isObject = true;
        $inner = (string) $firstchild->string;
        if (!class_exists($inner)) {
          trigger_error("The script tried to execute a method or " .
                        "access a property of an incomplete object. " .
                        "Please ensure that the class definition \"" .
                        $inner . "\" of the object you are trying to " .
                        "operate on was loaded _before_ unserialize()" .
                        " gets called or provide a __autoload() " .
                        "function to load the class definition " ,
                        E_ERROR);
          break;
        }
        $reflect  = new ReflectionClass($inner);
        $array = $reflect->newInstance();
      }
    }
    foreach ($subchildren as $subchild) {
      $returnArray = _wddx_deserialize_recursive($subchild);
      if ($isObject) {
        if (is_object($returnArray)) {
          if ($isFirst) {
            $isFirst = false;
            continue;
          }
          foreach ($returnArray as $key => $value) {
            $array->{$key} = $value;
          }
        }
      }
      else{
        if (is_array($returnArray)) {
          $array = $array + $returnArray;
        }
      }
    }
    return $array;
  }

  //var
  if ($type == "var" || $type == "field") {
    if (!count($subchildren)) {
      return null;
    }
    $subchild = $subchildren[0];
    if (!empty($node["name"])) {
      $key = (string)($node["name"]);
      $array[$key] = _wddx_deserialize_recursive($subchild);
      if ($type == "field") {
        $subarray = array();
          foreach ($subchildren as $subchild) {
              array_push(&$subarray, _wddx_deserialize_recursive($subchild));
          }
        $array[$key] = $subarray;
      }
      return $array;
    }
    return _wddx_deserialize_recursive($subchild);
  }

  return null;
}

/**
 * Ends a WDDX packet with the specified ID
 *
 * @param resource $packet_id - packet_id   A WDDX packet, returned by
 *   wddx_packet_start().
 *
 * @return string - Returns the string containing the WDDX packet.
 */
<<__Native>>
function wddx_packet_end(resource $packet_id): string;

/**
 * Starts a new WDDX packet with structure inside it
 *
 * @param string $comment - comment   An optional comment string.
 *
 * @return resource - Returns a packet ID for use in later functions, or
 *   FALSE on error.
 */
<<__Native>>
function wddx_packet_start(?string $comment = NULL): resource;

/**
 * Serialize a single value into a WDDX packet
 *
 * @param mixed $var - var   The value to be serialized
 * @param string $comment - comment   An optional comment string that
 *   appears in the packet header.
 *
 * @return string - Returns the WDDX packet, or FALSE on error.
 */
<<__Native>>
function wddx_serialize_value(mixed $var,
                              ?string $comment = NULL): string;

/**
 * Serialize variables into a WDDX packet
 *
 * @param mixed $var_name - var_name   Can be either a string naming a
 *   variable or an array containing strings naming the variables or
 *   another array, etc.
 * @param mixed $... - ...
 *
 * @return string - Returns the WDDX packet, or FALSE on error.
 */
<<__Native("ActRec", "ReadsCallerFrame")>>
function wddx_serialize_vars(...): string;
