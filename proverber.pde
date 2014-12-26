
// TODO automatize checks
// TODO name objects better to differentiate xml and Chunk class
// TODO add choices (i.e either common or proper noun)
// TODO how to know if "a" or "an" should be used (it needs to stay generic)
// TODO generate a large bunch of them, spot duplicates and then either remove very specific parameters or expand dico to balance the results 
// TODO you can say "it favors the bold" but possibly not "it favors the far", define an attribute to make the distinction
// TODO "there's no place like home" but "there's no animal like a duck", define attribute to know if it has to add "a" before the noun
// TODO make adverbs a declension of adjectives ?
// TODO crawl the web and flood fill that database
// TODO define a list of obligatory fields for each nature of words
// TODO if a required declension is not present ask for another word

void setup() {
}

void keyPressed() {
  if (keyCode==ENTER) println(generate(-1));
  if (keyCode==TAB) {
    for (int i=0; i<23; i++) {
      ArrayList<String> ps = new ArrayList<String>();
      int dup=0;
      for (int j=0; j<500; j++) {
        String p = generate(i);
        boolean found=false;
        for (int k=0; k<ps.size ( )&& !found; k++) if (p.equals(ps.get(k))) found=true;
        if (found) dup++;
        ps.add(p);
      }
      println (i+" : "+dup);
    }
  }
}

void draw() {
}

String generate(int proverbId) {
  XML dico = loadXML(dataPath("dico.xml"));
  XML proverbs = loadXML(dataPath("proverbs.xml"));
  if (proverbId==-1) proverbId = floor(random(proverbs.getChildren("proverb").length));
  // println("proverbId = " + proverbId);
  XML definedToGenerate = proverbs.getChildren("proverb")[proverbId].getChild("define");
  XML structureToGenerate = proverbs.getChildren("proverb")[proverbId].getChild("structure");
  ArrayList<XML> chunks = new ArrayList<XML>();
  int[] sentence = new int[structureToGenerate.getChildren("chunk").length];
  if (definedToGenerate!=null) for (XML chunk : definedToGenerate.getChildren ("chunk")) chunks.add(chunk);
  if (structureToGenerate!=null) {
    for (int i=0; i<structureToGenerate.getChildren ("chunk").length; i++) {
      XML chunk = structureToGenerate.getChildren ("chunk")[i];
      sentence[i]=chunks.size();
      chunks.add(chunk);
    }
  }
  Chunk[] result = new Chunk[chunks.size()];
  for (int i=0; i<chunks.size (); i++) {
    result[i] = new Chunk();
    if (!result[i].defined) generateChunk(chunks, result, i, dico);
  }
  result[sentence[0]].text=result[sentence[0]].text.substring(0, 1).toUpperCase()+result[sentence[0]].text.substring(1, result[sentence[0]].text.length());
  // print("proverb : ");
  String sentenceStr = "";
  for (int i=0; i<sentence.length; i++) sentenceStr += result[sentence[i]].text;
  // println(sentence);
  return sentenceStr;
}

class Chunk {
  String text;
  XML word;
  int id;
  boolean defined=false;
}

void generateChunk(ArrayList<XML> chunks, Chunk[] result, int index, XML dico) {
  XML chunk = chunks.get(index);
  if (chunk.getString("type").equals("static")) {
    result[index]=new Chunk();  
    result[index].text = chunk.getString("text");
    result[index].defined = true;
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
          for (String attrName : check.listAttributes ()) {
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
    catch (Exception e) {
      println("(pick word) : "+index+" : "+e);
    }

    // process "declension" statements
    try {
      for (XML declension : chosenWord.getChildren ("declension")) {
        boolean declensionIsOk=true;
        if (chunk.getChildren("declension").length>0) {
          for (int d=0; d<chunk.getChild ("declension").listAttributes().length; d++) {
            String thisAttribute = chunk.getChild("declension").listAttributes()[d];
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
          result[index] = new Chunk();
          result[index].text = declension.getString("text");
          result[index].word = chosenWord;
          result[index].defined=true;
        }
      }
    } 
    catch (Exception e) {
      println("(process declension statements) : "+index+" : "+e);
    }

    // process "info" statements
    for (XML info : chunk.getChildren ("info")) result[index].id = info.getInt("id");
  }
}

