<?php

$collation_name = "utf8_distinct_ci";
$collation_id   = 252;

$basechars = array();

// scann the full unicode basic plane minus the 7bit ASCII part
for ($codepoint = 0x0080; $codepoint < 0xFFFF; $codepoint++) {

  // simple UTF8 encoder
  if ($codepoint < 0x800) {
    $u1 = 0xC0 + ($codepoint >> 6);
    $u2 = 0x80 + ($codepoint & 0x3F);
    $utf8 = chr($u1).chr($u2);
  } else {
    $u1 = 0xE0 + ($codepoint >> 12);
    $u2 = 0x80 + (($codepoint >> 6) & 0x3F);
    $u3 = 0x80 + ($codepoint & 0x3F);
    $utf8 = chr($u1).chr($u2).chr($u3);
  }

  // normalizing using NFKD (Compatibility Decomposition)
  $normalized = Normalizer::normalize($utf8, Normalizer::FORM_KD);

  // check for combinations of a regular ASCII character
  // followed by addditional modifiers
  if (ctype_alpha($normalized[0]) && (strlen($normalized) > 1)) {
    $base = $normalized[0];
    $upper_base = strtoupper($base);

    // initialize letter detail array if not already done
    if (!isset($basechars[$upper_base])) {
      $basechars[$upper_base] = array();
    }
   
    $basechars[$upper_base][$codepoint]
      = array("utf8" => $utf8, // utf8 encoded codepoint
          "mods" => substr($normalized,1), // modifiers only (for sorting)
          "base" => $base // for secondary sorting
          );
  }
}

// sort by base character
ksort($basechars);

// start output
echo "  <collation name='$collation_name' id='$collation_id'>\n";
echo "    <rules>\n";

foreach ($basechars as $base => $extra) {
  // start new letter
  echo "\n      <reset>$base</reset>\n";

  // sort by mod codes, then base character case
  uasort($extra, "sortfunc");

  // remember mod codes from previous codepoint
  $prev_mods = "";

  // iterate over all codepoints for base letter (sorted)
  foreach ($extra as $codepoint => $details) {
    // create primary <p> entries for new mod combinations
    // tertiary <t> for lower case mod following upper case
    $tag = ($prev_mods == $details["mods"]) ? "t":"p";
   
    // generate output
    printf("      <$tag>\\u%04x</$tag><!-- %s -->\n", $codepoint, $details["utf8"]);

    // remember mod codes
    $prev_mods = $details["mods"];
  }
}

// finish output
echo "\n";
echo "    </rules>\n";
echo "  </collation>\n";


// sort by mods first, base char 2nd
function sortfunc($a, $b) {
  $test1 = strcmp($a["mods"], $b["mods"]);
  if ($test1 != 0) return $test1;

  return strcmp($a["base"], $b["base"]);
}
