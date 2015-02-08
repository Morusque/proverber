<?php

$dicoUrl = 'reducedDico.xml';
$dicoDoc = new DOMDocument('1.0', 'utf-8');
$dicoDoc->Load($dicoUrl);

$proverbsUrl = 'proverbs.xml';
$proverbsDoc = new DOMDocument('1.0', 'utf-8');
$proverbsDoc->Load($proverbsUrl);

$question = Array();
if (strlen($questionStr)>0) $question = explode(" ",$questionStr);

$entirePool = Array();// XML array
foreach ($dicoDoc->getElementsByTagName("word") as $word) {
	$entirePool[]=$word;
}

return generate(-1,$proverbsDoc,$dicoDoc,$entirePool, $question);
// echo generate(-1,$proverbsDoc,$dicoDoc,$entirePool,$question);

function generate($proverbId,$proverbsDoc,$dicoDoc,$entirePool, $question) {
  if ($proverbId==-1) $proverbId = rand(0,$proverbsDoc->getElementsByTagName("proverb")->length-1);
  $definedToGenerate = $proverbsDoc->getElementsByTagName("proverb")->item($proverbId)->getElementsByTagName("define")->item(0);
  $structureToGenerate = $proverbsDoc->getElementsByTagName("proverb")->item($proverbId)->getElementsByTagName("structure")->item(0);
  $chunks = Array();
  $sentence = Array();
  if ($definedToGenerate!=null) {
	for ($i=0; $i<$definedToGenerate->getElementsByTagName("chunk")->length; $i++) {
		$chunk = $definedToGenerate->getElementsByTagName("chunk")->item($i);
		$chunks[]=$chunk;
	}
  }
  if ($structureToGenerate!=null) {
    for ($i=0; $i<$structureToGenerate->getElementsByTagName("chunk")->length; $i++) {
      $chunk = $structureToGenerate->getElementsByTagName("chunk")->item($i);
      $sentence[$i] = count($chunks);
      $chunks[]=$chunk;
    }
  }
  $result = Array();// Chunk[]
  for ($i=0; $i<count($chunks); $i++) {
    $result[$i] = new Chunk();
    if (!$result[$i]->defined) generateChunk($chunks, $result, $i, $dicoDoc, $entirePool, $question);
  }
  $result[$sentence[0]]->text = strtoupper(substr($result[$sentence[0]]->text,0,1)) . substr($result[$sentence[0]]->text,1);
  $sentenceStr = "";
  for ($i=0; $i<count($sentence); $i++) $sentenceStr .= $result[$sentence[$i]]->text;
  return $sentenceStr;
}

class Chunk {
  var $text;// string
  var $word;// xml node
  var $id;// int
  var $defined=false;// boolean
}

function generateChunk($chunks, $result, $index, $dico, $entirePool, $question) {// $chunks[] = xml nodes, $result[] = Chunk objects, $index = index of both $chunks and $results, $dico = XML
  $chunk = $chunks[$index];// xml node
  if ($chunk->getAttribute("type")=="static") {
	// $result[$index] = new Chunk();
    $result[$index]->text = $chunk->getAttribute("text");
    $result[$index]->defined = true;
  }

  if ($chunk->getAttribute("type")=="query") {
    // process "pool" statements
    $pool = Array();// XML array
    if ($chunk->getElementsByTagName("pool")->length==0) {
		$pool = array_values($entirePool);
	} else {
      foreach ($chunk->getElementsByTagName("pool") as $poolIndic) {
        if ($poolIndic->getAttribute("type")=="link") {
          $targetId = $poolIndic->getAttribute("id");
          $comparedChunkIndex = -1;
          for ($i=0; $i<count($chunks); $i++) if ($chunks[$i]->getElementsByTagName("info")->length>0) if ($chunks[$i]->getElementsByTagName("info")->item(0)->getAttribute("id")==$targetId) $comparedChunkIndex = $i;
          if ($comparedChunkIndex>=0) {
            if ($result[$comparedChunkIndex]==null) generateChunk($chunks, $result, $comparedChunkIndex, $dico, $entirePool, $question);// TODO it should never be null but instead "not defined"
            else if (!$result[$comparedChunkIndex]->defined) generateChunk($chunks, $result, $comparedChunkIndex, $dico, $entirePool, $question);
            $compared = $result[$comparedChunkIndex]->word;// xml node
            foreach ($compared->getElementsByTagName("link") as $link) {
              if ($link->getAttribute("relation")==($poolIndic->getAttribute("relation"))) {
                $finalTargetId = $link->getAttribute("id");// int
                foreach ($entirePool as $word) {// xml node
                  if ($word->getAttribute("id")==$finalTargetId) $pool[]=$word;
                }
              }
            }
          }
        }
        if ($poolIndic->getAttribute("type")=="defined") {
          $targetId = $poolIndic->getAttribute("id");// int
          $comparedChunkIndex = -1;// int
          for ($i=0; $i<count($chunks); $i++) if ($chunks[$i]->getElementsByTagName("info")->length>0) if ($chunks[$i]->getElementsByTagName("info")->item(0)->getAttribute("id")==$targetId) $comparedChunkIndex = $i;
          if ($comparedChunkIndex>=0) {
            if ($result[$comparedChunkIndex]==null) generateChunk($chunks, $result, $comparedChunkIndex, $dico, $entirePool, $question);// TODO it should never be null but instead "not defined"
            else if (!$result[$comparedChunkIndex]->defined) generateChunk($chunks, $result, $comparedChunkIndex, $dico, $entirePool, $question);
            $compared = $result[$comparedChunkIndex]->word;// xml
            $pool[] = $compared;
          }
        }
      }
    }

    // process "property" statements
	$currentPoolSize = count($pool);
    for ($j=0; $j<$currentPoolSize; $j++) {
      $allConditionsMatch=true;// boolean
      foreach ($chunk->getElementsByTagName("property") as $condition) {// xml node
        $type = $condition->getAttribute("type");// string
        $value = $condition->getAttribute("value");// string
        $matchFound=false;// boolean
        foreach ($pool[$j]->getElementsByTagName("property") as $property) {// xml node
          if ($property->getAttribute("type")==$type) {
            if ($property->getAttribute("value")==$value) {
              $matchFound=true;
            }
          }
        }
        if (!$matchFound) $allConditionsMatch=false;
      }
      if (!$allConditionsMatch) unset($pool[$j]);
    }
	$pool = array_values($pool);

    // process "check" statements
    foreach ($chunk->getElementsByTagName("check") as $check) {
	  $currentPoolSize = count($pool);
      for ($j=0; $j<$currentPoolSize; $j++) {
        $oneMatchFound = false;// boolean
        foreach ($pool[$j]->getElementsByTagName($check->getAttribute("node")) as $node) {// xml node
          $matchesSoFar = true;// boolean
          foreach ($check->attributes as $attr) {// string
			$attrName = $attr->nodeName;
            if ($attrName!="node") {
              if (!$node->hasAttribute($attrName)) $matchesSoFar=false;
              else if ($check->getAttribute($attrName)!=($node->getAttribute($attrName))) $matchesSoFar=false;
            }
          }
          if ($matchesSoFar) $oneMatchFound=true;
        }
        if (!$oneMatchFound) unset($pool[$j]);
      }
    }
	$pool = array_values($pool);

    // TODO process "elude" statements (for both entire words, words to be omitted based on specific attributes)

	$chosenWord=null;// xml node
	
	// try to pick word to fit with the question
	$qwFound=false;
	if (count($question)>0) {
		foreach ($pool as $word) {
			foreach ($word->getElementsByTagName("declension") as $decl) {
				$wordStr = $decl->getAttribute("text");
				foreach ($question as $qWord) {
					if ($wordStr==$qWord) {
						$qwFound = true;
						$chosenWord = $word;
					}
				}
			}
		}
	}
	if ($chosenWord!=null) {
		for ($i=0;$i<count($question);$i++) {
			$qWord = $question[$i];
			foreach ($chosenWord->getElementsByTagName("declension") as $decl) {
				$wordStr = $decl->getAttribute("text");
				if ($wordStr==$qWord) {
					unset($question[$i]);
				}
			}
		}
	}

    // pick word
	if ($chosenWord==null) {
		try {
		  $chosenWord = $pool[rand(0,count($pool)-1)];
		} catch (Exception $e) {
			echo "(pick word) : ".$index." : ".$e->getMessage();
		}
	}

    // process "declension" statements
    try {
		if ($chosenWord!=null) {// TODO it should never be null... I only added this for the case when I was testing with an empty pool	
		  foreach ($chosenWord->getElementsByTagName("declension") as $declension) {// xml node
			$declensionIsOk=true;// boolean
			if ($chunk->getElementsByTagName("declension")->length>0) {
			  for ($d=0; $d<$chunk->getElementsByTagName("declension")->item(0)->attributes->length; $d++) {
				$thisAttribute = $chunk->getElementsByTagName("declension")->item(0)->attributes->item($d)->nodeName;				
				if ($declension->hasAttribute($thisAttribute)) {
				  if ($declension->getAttribute($thisAttribute)!=($chunk->getElementsByTagName("declension")->item(0)->getAttribute($thisAttribute))) {
					$declensionIsOk=false;
				  }
				} else {
				  // TODO still not sure if there should be this or not : declensionIsOk=false;
				}
			  }
			}			
			if ($declensionIsOk) {
			  // $result[$index] = new Chunk();
			  $result[$index]->text = $declension->getAttribute("text");
			  $result[$index]->word = $chosenWord;
			  $result[$index]->defined=true;
			}
		  }		  
	  }
    } catch (Exception $e) {
		echo "(process declension statements) : ".$index." : ".$e->getMessage();
    }

    // process "info" statements
    foreach ($chunk->getElementsByTagName("info") as $info) $result[$index]->id = $info->getAttribute("id");// xml node
  }

}

?>
