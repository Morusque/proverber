
// TODO automatize checks
// TODO name objects better to differentiate xml and Chunk class
// TODO add choices (i.e either common or proper noun)

void setup() {
  generate();
}

void keyPressed() {
  generate();
}

void draw() {
}

void generate() {
  XML dico = loadXML(dataPath("dico.xml"));
  XML proverbs = loadXML(dataPath("proverbs.xml"));
  int proverbId = floor(random(16));
  println("proverbId = " + proverbId);
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
  print("proverb : ");
  for (int i=0; i<sentence.length; i++) print(result[sentence[i]].text);
  println("");
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

    // TODO process "elude" statements

    // pick word
    XML chosenWord = pool.get(floor(random(pool.size())));

    // process "declension" statements
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

    // process "info" statements
    for (XML info : chunk.getChildren ("info")) result[index].id = info.getInt("id");
  }
}

