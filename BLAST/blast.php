<?php
session_start();
#include once config quitado porque lo he compiado y pegado
$blastHome = "/home/dbw00/blast";
$blastDbsDir = "$blastHome/DBS";
$blastExe = "$blastHome/bin/blastp";
$blastDbs = ["SwissProt" => "sprot", "PDB" => "pdb"];
$input_file_path = "/tmp/input.fasta";
$database_to_use=$_POST['DB'];
$blastCmdLine = "$blastExe -db $blastDbsDir/" . $blastDbs[$database_to_use] . " -evalue 0.001 -max_target_seqs 100 -outfmt \"6 sseqid stitle evalue\" -query $input_file_path ";


#priority to uploaded files
if ($_FILES['uploaded_file']['name']) {
    $_POST['fasta']=  file_get_contents($_FILES['uploaded_file']['tmp_name']);
}


if (!$_POST['fasta']) {
  header('Location: index.html');
  exit();
} else {

    if (!isFasta($_POST['fasta'])) {
        $rawSequence = (string) strtoupper($_POST['fasta']);
        if(!isRawSequence($rawSequence)){
          header('Location: index.html');
          exit();
        }else{
          $fasta = $rawSequence;
        }
    } else {
        $fasta = $_POST['fasta'];
    }
    $fasta_file = fopen($input_file_path, "w") or die ("File cannot be opened, select another file");
    fwrite($fasta_file, $fasta);
    fclose($fasta_file);
}

exec($blastCmdLine, $outputblast, $status);
if (0 === $status) {
print headerDBW("Search results");
    ?>
<p><br>Number of Hits: <?php print count($outputblast) ?> <br></p>
<p class="button"><a href="index.html?new=1">New Search</a></p>
<table border="2" cellspacing="2" cellpadding="3" id="blastTable">
        <thead>
            <tr>
		<th>idCode</th>
                <th>Header</th>
                <th>E. value</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 1; $i < count($outputblast); $i++) { ?>
                <tr>
                    <?php
                        $line_values = explode("\t", $outputblast[$i]);
                        for($index_line_values = 0; $index_line_values < count($line_values); $index_line_values++) {
                        ?>
                        <td><?php
		                if($index_line_values == 0 ){
		                  $seq_id = explode("_",$line_values[$index_line_values])[0];
		                  if ($database_to_use=="PDB"){$url = "http://www.rcsb.org/structure/$seq_id";}
		                  if ($database_to_use=="SwissProt"){
					$seq_id=substr($seq_id,3,6);
					$url = "https://www.uniprot.org/uniprot/$seq_id";}
		                  print "<a href=\"$url\" target=\"_blank\">$line_values[$index_line_values] </a>";
		                }else{
		                  print "<p>$line_values[$index_line_values]</p>";
		                }
                         ?> </td>
                    <?php } ?>
                </tr>
            <?php } ?>
        </tbody>
    </table>
<p class="button"><a href="index.html?new=1">New Search</a></p>
<script type="text/javascript">
    $(document).ready(function () {
        $('#blastTable').DataTable();
    });
</script>
<?php } else {
     echo "Problem: $status";
}


function isFasta($file) {
    return (substr($file,0,1) == ">");
}

function isRawSequence($str){
    return True;
    $protein_letters = "ACDEFGHIKLMNPQRSTVWY*";
    $str=str_split($str);
    for ($i = 0; $i < count($str); $i++){
      $char = (string) $str[$i];
      if (mb_strpos("ACDEFGHIKLMNPQRSTVWY*","B") == false) {
          return True;
      }
    }
    return True;
}

function headerDBW($title) {
    return "<html lang=\"en\">
<head>
<meta charset=\"utf-8\">
    <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
<title>$title</title>
       <!-- Bootstrap styles -->
    <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css\" integrity=\"sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u\" crossorigin=\"anonymous\">
  
    <!-- IE 8 Support-->
    <!--[if lt IE 9]>
      <script src=\"https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js\"></script>
      <script src=\"https://oss.maxcdn.com/respond/1.4.2/respond.min.js\"></script>
    <![endif]--> 
        <link rel=\"stylesheet\" href=\"DataTable/jquery.dataTables.min.css\"/>
        <script type=\"text/javascript\" src=\"DataTable/jquery-2.2.0.min.js\"></script>
        <script type=\"text/javascript\" src=\"DataTable/jquery.dataTables.min.js\"></script>

</head>
<body bgcolor=\"#ffffff\">
<div class= \"container\">
<h1>$title</h1>
";}

?>
