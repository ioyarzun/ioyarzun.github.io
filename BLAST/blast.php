<!DOCTYPE html>
<html lang="en" dir="ltr">

  <head bgcolor=\"#ffffff\">
    <title>Iñigo Oyarzun´s page</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="assets/css/main.css" />
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>

    <link rel="stylesheet" href="DataTable/jquery.dataTables.min.css"/>
    <script type="text/javascript" src="DataTable/jquery-2.2.0.min.js"></script>
    <script type="text/javascript" src="DataTable/jquery.dataTables.min.js"></script>
  </head>

  <body>
    <header id="header">
			<h1><strong><a href="../index.html">Home</a></strong> </h1>
			<nav id="nav">
				<ul>
					<li><a href="../datamodel/datamodel.html">DATA MODEL</a></li>
          <li><a href="../random_entertainment/somemusic.html">Some music</a></li>
          <li><a href="../random_entertainment/index.html">Random entertainment</a></li>
				</ul>
			</nav>
		</header>
<?php
session_start();

$blastDbs = ["SwissProt" => "swissprot", "PDB" => "pdb"];
$database_to_use = $blastDbs[$_POST['DB']];

// $file = "iteremo.txt";
// $switcher=False;
// $handle = fopen($file, "r");

// Read FASTA file through HTML file upload or directly and encode
function fas_read($file) {
  $query = '';
  $handle = fopen($file, "r");
  if ($handle) {
    while (($line = fgets($handle)) !== false) {
      if($line[0]!=">" and !strstr($line, ' ')){
        $query .= $line;
      }
    }
    fclose($handle);
  }
  return $query;
}


// #priority to uploaded files
if ($_FILES['uploaded_file']['name']) {
    $_POST['fasta']=  fas_read($_FILES['uploaded_file']['tmp_name']);
}

// Read FASTA sequence from the HTML textbox and encode
$encoded_query = urlencode($_POST["fasta"]);

// Build the request
$data = array('CMD' => 'Put', 'PROGRAM' => 'blastp', 'DATABASE' => $database_to_use, 'EXPECT' => 0.00000001, 'FORMAT_TYPE' => 'Text', 'QUERY' => $encoded_query);
$options = array(
  'http' => array(
    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
    'method'  => 'POST',
    'content' => http_build_query($data)
  )
);
$context  = stream_context_create($options);

// Get the response from BLAST
$result = file_get_contents("https://blast.ncbi.nlm.nih.gov/blast/Blast.cgi", false, $context);

// Parse out the request ID
preg_match("/^.*RID = .*\$/m", $result, $ridm);
$rid = implode("\n", $ridm);
$rid = preg_replace('/\s+/', '', $rid);
$rid = str_replace("RID=", "", $rid);

// Parse out the estimated time to completion
preg_match("/^.*RTOE = .*\$/m", $result, $rtoem);
$rtoe = implode("\n", $rtoem);
$rtoe = preg_replace('/\s+/', '', $rtoe);
$rtoe = str_replace("RTOE=", "", $rtoe);

// Maximum execution time of webserver (optional)
ini_set('max_execution_time', $rtoe+6000);

//converting string to long (sleep() expects a long)
$rtoe = $rtoe + 0;

// Wait for search to complete
sleep($rtoe);

// Poll for results
while(true) {
  sleep(10);

  $opts = array(
  	'http' => array(
      'method' => 'GET'
  	)
  );
  $contxt = stream_context_create($opts);
  $reslt = file_get_contents("https://blast.ncbi.nlm.nih.gov/blast/Blast.cgi?CMD=Get&FORMAT_OBJECT=SearchInfo&RID=$rid", false, $contxt);

  if(preg_match('/Status=WAITING/', $reslt)) {
  	//print "Searching...\n";
    continue;
  }

  if(preg_match('/Status=FAILED/', $reslt)) {
    print "Search $rid failed, please report to blast-help\@ncbi.nlm.nih.gov.\n";
    exit(4);
  }

  if(preg_match('/Status=UNKNOWN/', $reslt)) {
    print "Search $rid expired.\n";
    exit(3);
  }

  if(preg_match('/Status=READY/', $reslt)) {
    if(preg_match('/ThereAreHits=yes/', $reslt)) {
      //print "Search complete, retrieving results...\n";
      break;
  	} else {
      print "No hits found.\n";
      exit(2);
  	}
  }

  // If we get here, something unexpected happened.
  exit(5);
} // End poll loop

// Retrieve and display results
$opt = array(
  'http' => array(
  	'method' => 'GET'
  )
);
$content = stream_context_create($opt);
$output = file_get_contents("https://blast.ncbi.nlm.nih.gov/blast/Blast.cgi?CMD=Get&FORMAT_TYPE=Text&RID=$rid", false, $content);


$handle = explode("\n",$output);
$switcher = False;
if ($handle) {
  foreach ($handle as $line) {
    if (strstr($line, 'ALIGNMENTS')){
      $switcher = False;
      echo "</tbody> </table>";
    }

    if ($switcher == True){
      // aqui la magia del formato
      if(strlen($line) > 4){
        $line_values = explode(" ", $line);
        $line_values = array_values(array_filter($line_values));
        $nr_ele = count($line_values);

        $seq_id = $line_values[0];
        $Bit_score = $line_values[$nr_ele - 3];
        $E_value = $line_values[$nr_ele - 2];
        $al_identity = $line_values[$nr_ele - 1];
        $desc = implode(" ",array_splice($line_values , 1, $nr_ele  -4));

        if ($database_to_use=="pdb"){
          $idurl = explode("_",$seq_id)[0];
          $url = "http://www.rcsb.org/structure/$idurl";
        }
        if ($database_to_use=="swissprot"){
          $idurl = explode(".",$seq_id)[0];
          $url = "https://www.uniprot.org/uniprot/$idurl";
        }
        ?>
        <tr>
          <td><?php print "<a href=\"$url\" target=\"_blank\">$seq_id</a>"  ?></td>
          <td><?php print $desc ?></td>
          <td><?php print $Bit_score  ?></td>
          <td><?php print $E_value  ?></td>
          <td><?php print $al_identity ?></td>
        </tr>
        <?php
      }
    }

    if (strstr($line, 'Sequences producing significant alignments')){
      ?>
      <!-- <center><a href="index.html?new=1" class="button special" >New Search</a></center> -->
      <center><a href="index.html?new=1" class="button" >New Search</a></center>
      <table border="2" cellspacing="2" cellpadding="3" id="blastTable">
      <thead>
          <tr>
	            <th>idCode</th>
              <th>Header</th>
              <th>Bit Score</th>
              <th>E. value</th>
              <th>Identity</th>
          </tr>
      </thead>
      <tbody>
      <?php
      $switcher=True;
    }
  }
?>
<script type="text/javascript">
    $(document).ready(function () {
        $('#blastTable').DataTable({
          "order": [[ 2, "desc" ]]
        });
    });
</script>
<?php
}


?>
</section>
<footer id="footer">
  <div class="container">
    <ul class="icons">
      <li><a href="https://www.linkedin.com/in/iñigo-oyarzun-269090161" target="_blank" class="icon fa-linkedin"></a></li>
      <li><a href="https://www.facebook.com/inigo.oyarzun.3" target="_blank"  class="icon fa-facebook"></a></li>

    </ul>
    <ul class="copyright">
      <li> My Social Media </li>
    </ul>
  </div>
</footer>
  <script src="../assets/js/jquery.min.js"></script>
  <script src="../assets/js/skel.min.js"></script>
  <script src="../assets/js/util.js"></script>
  <script src="../assets/js/main.js"></script>
  </body>
</html>

