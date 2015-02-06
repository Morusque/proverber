<?php

$dicoUrl = 'dico.xml';
$dicoDoc = new DOMDocument('1.0', 'utf-8');
$dicoDoc->Load($dicoUrl);

$proverbsUrl = 'proverbs.xml';
$proverbsDoc = new DOMDocument('1.0', 'utf-8');
$proverbsDoc->Load($proverbsUrl);

echo 'coucou<br/>';

echo generate(-1,$proverbsDoc,$dicoDoc);

function generate($proverbId,$proverbsDoc,$dicoDoc) {
  if ($proverbId==-1) $proverbId = rand(0,$proverbsDoc->getElementsByTagName("proverb")->length-1);
  $definedToGenerate = $proverbsDoc->getElementsByTagName("proverb")->item($proverbId)->getElementsByTagName("define")->item(0);
  $structureToGenerate = $proverbsDoc->getElementsByTagName("proverb")->item($proverbId)->getElementsByTagName("structure")->item(0);
  $chunks = Array();
  $sentence = Array();
  if ($definedToGenerate!=null) foreach ($definedToGenerate->getElementsByTagName("proverb") as $chunk) $chunks[] = $chunk;
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
    if (!$result[$i]->defined) generateChunk($chunks, $result, $i, $dicoDoc);
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

// ---fin de la traduction là--- 
/*
function generateChunk(ArrayList<XML> chunks, Chunk[] result, $index, XML dico) {
  XML chunk = chunks.get($index);
  if (chunk.getString("type").equals("static")) {
    result[$index]=new Chunk();
    result[$index].text = chunk.getString("text");
    result[$index].defined = true;
  }
  if (chunk.getString("type").equals("query")) {

    // process "pool" statements
    ArrayList<XML> pool = new ArrayList<XML>();
    if (chunk.getChildren("pool").length==0) {
      for (XML word : dico.getChildren ("word")) {
        pool.add(word);
      }
    } else {
      for (XML poolIndic : chunk.getChildren ("pool")) {
        if (poolIndic.getString("type").equals("link")) {
          int targetId = poolIndic.getInt("id");
          int comparedChunkIndex = -1;
          for (int i=0; i<chunks.size (); i++) if (chunks.get(i).getChildren("info").length>0) if (chunks.get(i).getChild("info").getInt("id")==targetId) comparedChunkIndex = i;
          if (comparedChunkIndex>=0) {
            if (result[comparedChunkIndex]==null) generateChunk(chunks, result, comparedChunkIndex, dico);// TODO it should never be null but instead "not defined"
            else if (!result[comparedChunkIndex].defined) generateChunk(chunks, result, comparedChunkIndex, dico); 
            XML compared = result[comparedChunkIndex].word;
            for (XML link : compared.getChildren ("link")) {
              if (link.getString("relation").equals(poolIndic.getString("relation"))) {
                int finalTargetId = link.getInt("id");
                for (XML word : dico.getChildren ("word")) {
                  if (word.getInt("id")==finalTargetId) pool.add(word);
                }
              }
            }
          }
        }
        if (poolIndic.getString("type").equals("defined")) {
          int targetId = poolIndic.getInt("id");
          int comparedChunkIndex = -1;
          for (int i=0; i<chunks.size (); i++) if (chunks.get(i).getChildren("info").length>0) if (chunks.get(i).getChild("info").getInt("id")==targetId) comparedChunkIndex = i;
          if (comparedChunkIndex>=0) {
            if (result[comparedChunkIndex]==null) generateChunk(chunks, result, comparedChunkIndex, dico);// TODO it should never be null but instead "not defined"
            else if (!result[comparedChunkIndex].defined) generateChunk(chunks, result, comparedChunkIndex, dico);
            XML compared = result[comparedChunkIndex].word;
            pool.add(compared);
          }
        }
      }
    }

    // process "property" statements
    for (int j=0; j<pool.size (); j++) {
      boolean allConditionsMatch=true;
      for (XML condition : chunk.getChildren ("property")) {
        String type = condition.getString("type");
        String value = condition.getString("value");
        boolean matchFound=false;
        for (XML property : pool.get (j).getChildren ("property")) {
          if (property.getString("type").equals(type)) {
            if (property.getString("value").equals(value)) {
              matchFound=true;
            }
          }
        }
        if (!matchFound) allConditionsMatch=false;
      }
      if (!allConditionsMatch) pool.remove(j--);
    }

    // process "check" statements
    for (XML check : chunk.getChildren ("check")) {
      for (int j=0; j<pool.size (); j++) {
        boolean oneMatchFound = false;
        for (XML node : pool.get (j).getChildren (check.getString ("node"))) {
          boolean matchesSoFar = true;
          for (String attrName : listAttributesJs (check)) {
            if (!attrName.equals("node")) {
              if (!node.hasAttribute(attrName)) matchesSoFar=false;
              else if (!check.getString(attrName).equals(node.getString(attrName))) matchesSoFar=false;
            }
          }
          if (matchesSoFar) oneMatchFound=true;
        }
        if (!oneMatchFound) pool.remove(j--);
      }
    }

    // TODO process "elude" statements (for both entire words, words to be omitted based on specific attributes)

    // pick word
    XML chosenWord=null;
    try {
      chosenWord = pool.get(floor(random(pool.size())));
    } 
    catch (Exception $e) {
		echo "(pick word) : "+$index+" : "+$e->getMessage());
    }

    // process "declension" statements
    try {
      for (XML declension : chosenWord.getChildren ("declension")) {
        boolean declensionIsOk=true;
        if (chunk.getChildren("declension").length>0) {
          for (int d=0; d<chunk.getChild ("declension").getAttributeCount(); d++) {
            String thisAttribute = listAttributesJs(chunk.getChild("declension"))[d];
            if (declension.hasAttribute(thisAttribute)) {
              if (!declension.getString(thisAttribute).equals(chunk.getChild("declension").getString(thisAttribute))) {
                declensionIsOk=false;
              }
            } else {
              // TODO not sure if there should be this or not : declensionIsOk=false;
            }
          }
        }
        if (declensionIsOk) {
          result[$index] = new Chunk();
          result[$index].text = declension.getString("text");
          result[$index].word = chosenWord;
          result[$index].defined=true;
        }
      }
    } 
    catch (Exception $e) {
		echo "(process declension statements) : "+$index+" : "+$e->getMessage());
    }

    // process "info" statements
    for (XML info : chunk.getChildren ("info")) result[index].id = info.getInt("id");
  }
}
*/

?>
