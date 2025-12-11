<?php
// api/lib/pdf2text.php
// A simple PDF to Text decoder (Native PHP, no Composer)
// Based on common loose implementations for basic text extraction.

class PDF2Text {
    public function decodePDF($filename) {
        $infile = @file_get_contents($filename, FILE_BINARY);
        if (empty($infile)) return "";

        $transformations = [];
        $texts = [];

        // Get all text objects
        preg_match_all("#BT[\s\r\n]+(.*?)[\s\r\n]+ET#s", $infile, $matches);
        
        foreach ($matches[1] as $textblock) {
            preg_match_all("#\((.*?)\)#s", $textblock, $atoms);
            foreach ($atoms[1] as $atom) {
                // Simple cleanup
                $texts[] = $atom;
            }
        }
        
        // If standard extraction fails, try a broader raw stream search (often needed for modern PDFs)
        if (count($texts) < 5) {
             preg_match_all("#stream[\s\r\n]+(.*?)[\s\r\n]+endstream#s", $infile, $stream_matches);
             $final_text = "";
             foreach($stream_matches[1] as $stream) {
                 // Try to decode if flate
                 $decoded = @gzuncompress($stream);
                 if ($decoded) {
                     // Extract parens
                     preg_match_all("#\((.*?)\)#s", $decoded, $atoms);
                     foreach ($atoms[1] as $atom) $final_text .= $atom . " ";
                     
                     // Extract Td/TJ
                     preg_match_all("#\[(.*?)\]#s", $decoded, $brackets);
                     foreach ($brackets[1] as $bracket) {
                          // Clean hex or spacing
                          $final_text .= strip_tags($bracket) . " "; 
                     }
                 }
             }
             if (strlen($final_text) > 20) return $this->cleanForJSON($final_text);
        }

        return $this->cleanForJSON(implode(" ", $texts));
    }
    
    // Clean weird characters to avoid JSON breaks
    private function cleanForJSON($str) {
        // Remove null bytes
        $str = str_replace("\0", "", $str);
        // Backslashes
        $str = stripslashes($str); 
        // Hex patterns (e.g. <0041>) - Simple removal
        $str = preg_replace("/<[0-9a-fA-F]+>/", "", $str);
        // UTF8 cleanup
        return mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    }
}
?>
