<script type="text/javascript">
  function showAndHide() {
    var $signsYes = $("input#SignsRadio1:radio:checked").val();
    var $signsNo = $("input#SignsRadio0:radio:checked").val();
    var $diagnoseYes = $("input#DiagnoseRadio1:radio:checked").val();
    var $diagnoseNo = $("input#DiagnoseRadio0:radio:checked").val();
    var $prescribedYes = $("input#PrescribedRadio1:radio:checked").val();
    var $prescribedNo = $("input#PrescribedRadio0:radio:checked").val();

    var $top3Answered = (($signsYes || $signsNo) && 
        ($diagnoseYes || $diagnoseNo) && ($prescribedYes || $prescribedNo));

    var $echoYes = $("input#EchoRadio1:radio:checked").val();
    var $echoNo = $("input#EchoRadio0:radio:checked").val();

    var $congestion = $("select#congestionSelect").val();

    var $part2BasicDone = ($echoYes || $echoNo) && $congestion != '';

    var $lvef = $("select#lvefSelect").val();

    var $lowLvefYes = $("input#LowLvefRadio1:radio:checked").val();
    var $lowLvefNo = $("input#LowLvefRadio0:radio:checked").val();
    var $wmaYes = $("input#WmaRadio1:radio:checked").val();
    var $wmaNo = $("input#WmaRadio0:radio:checked").val();
    var $ddYes = $("input#DdRadio1:radio:checked").val();
    var $ddNo = $("input#DdRadio0:radio:checked").val();
    var $msvdYes = $("input#MsvdRadio1:radio:checked").val();
    var $msvdNo = $("input#MsvdRadio0:radio:checked").val();

    var $part2EchoDone = $lvef != '' &&
                         ($lowLvefYes || $lowLvefNo) && 
                         ($wmaYes || $wmaNo) && 
                         ($ddYes || $ddNo) && 
                         ($msvdYes || $msvdNo);

    var $classification = $("select#classificationSelect").val();
    var $presentation = $("select#presentationSelect").val();

    var $ischemic = $("input#ischemic_flag").is(":checked");
    var $nonischemic = $("input#nonischemic_flag").is(":checked");
    var $unknown = $("input#unknown_flag").is(":checked");

    var $niValvular = $("input#ni_valvular_flag").is(":checked");
    var $niInfiltrative = $("input#ni_infiltrative_flag").is(":checked");
    var $niIm = $("input#ni_im_flag").is(":checked");
    var $niObstructive = $("input#ni_obstructive_flag").is(":checked");
    var $niRecreational = $("input#ni_recreational_flag").is(":checked");
    var $niPrescription = $("input#ni_prescription_flag").is(":checked");
    var $niHypertensive = $("input#ni_hypertensive_flag").is(":checked");
    var $niRenal = $("input#ni_renal_flag").is(":checked");
    var $niCardiomyopathy = $("input#ni_cardiomyopathy_flag").is(":checked");
    var $niCovid = $("input#ni_covid_flag").is(":checked");
    var $niSepsis = $("input#ni_sepsis_flag").is(":checked");
    var $niMetabolic = $("input#ni_metabolic_flag").is(":checked");
    var $niPd = $("input#ni_pd_flag").is(":checked");
    var $niHypertrophic = $("input#ni_hypertrophic_flag").is(":checked");
    var $niOther = $("input#ni_other_flag").is(":checked");

    var $moreInfoYes = $("input#MoreInfoRadio1:radio:checked").val();
    var $moreInfoNo = $("input#MoreInfoRadio0:radio:checked").val();

    var $part3BasicDone = $classification != '' && $presentation != '' &&
        ($ischemic || $nonischemic || $unknown) &&
        ($moreInfoYes || $moreInfoNo);

    var $part3NiDone = $niValvular || $niInfiltrative || $niIm || 
        $niObstructive || $niRecreational || $niPrescription ||
	$niHypertensive || $niRenal || $niCardiomyopathy ||
        $niCovid || $niSepsis || $niMetabolic || $niPd ||
        $niHypertrophic || $niOther;

    $("tr.part1").show();
    $("tr.part2").hide();
    $("tr.echoyes").hide();
    $("tr.part3").hide();
    $("div#nonischemic").hide();
    $("div#ni_other").hide();
    $("div#needMore").hide();
    $("tr#submit").hide();

    if ($top3Answered && ($signsNo || $diagnoseNo || $prescribedNo)) {
      $("tr#submit").show();
    } else if ($top3Answered) {  // all 3 yes
      $("tr.part2").show();

      if ($echoYes) {
        $("tr.echoyes").show();
      }

      if ($part2BasicDone && ($part2EchoDone || $echoNo)) {
        $("tr.part3").show();

        if ($nonischemic) {
          $("div#nonischemic").show();

          if ($niOther) {
            $("div#ni_other").show();
          }
        }

        if ($moreInfoYes) {
          $("div#needMore").show();
        }

        if ($part3BasicDone && (!$nonischemic || $part3NiDone)) {
            $("tr#submit").show();
        }
      }
    }
  }

  $(document).ready(function(){
    showAndHide();

    $("select").change(function () {
      showAndHide();
    });

    $("input[type=checkbox]").click(function () {
      showAndHide();
    });

    $("input").change(function () {
      showAndHide();
    });
  });

</script>

<div class="boxright" id="infobox" style="width: 300px; font-size: .95em">
	<h3>Review Instructions:</h3>
    <br />
    <div>View as: 
    <?php
    echo $html->link('.doc', '/files/' . $prefix . Event::REVIEW_INSTRUCTIONS);
    echo " | ";
    echo $html->link('.pdf', '/files/' . $prefix . Event::REVIEW_INSTRUCTIONS_PDF, array('target'=>'_blank'));
    ?>
    </div>
</div>

<h1>Review event: <?php echo $eventId; ?></h1>
                             
<p>
<?php
  echo "Date: " . $event['Event']['event_date'];
?>
</p>

<?php
    echo $form->create(null, array('controller' => 'events',
                                   'action' => 'review' . $reviewerNumber));
    echo $form->hidden('Event.id', array('value' => $eventId));
    echo $form->hidden(AppController::CAKEID_KEY,
                       array(
                           'value' => $session->read(AppController::ID_KEY)
                      ));
?>

<div class="indent1">

    <h2>Step 1: Review Charts</h2>
    
    <p>Review the packet for this event:</p>
    <ul>
        <li><?php
        $anchor = "Download charts for Event " . $eventId;
        echo $html->link($anchor, '/events/download/' .$eventId );
        ?></li>
    </ul>
    
    <h2>Step 2: Enter Decision</h2>
    <br />
    <?php
        echo $form->create(null, array('controller' => 'events',
                                       'action' => 'review' . $reviewerNumber));
        echo $form->hidden('Event.id', array('value' => $eventId));
        echo $form->hidden(AppController::CAKEID_KEY,
                           array(
                               'value' => $session->read(AppController::ID_KEY)
                          ));
    ?>
    
    <table id='reviewForm'>
<tr class='part1'>
  <th>Are there signs or symptoms of HF at any point, OR if there are no symptoms is there clearly documented HF that is compensated and on medication?</th>
  <td>
  <?php 
    echo $form->radio('Review.signs_flag', 
                      array(1 => 'Yes&nbsp;&nbsp;', 0 => 'No'), 
                      array('legend' => false, 'id' => 'signsRadio'));
  ?>
  </td>
</tr>
<tr class='part1'>
  <th>Did the physician diagnose HF and/or cardiomyopathy?</th>
  <td>
  <?php 
    echo $form->radio('Review.diagnose_flag', 
                      array(1 => 'Yes&nbsp;&nbsp;', 0 => 'No'), 
                      array('legend' => false, 'id' => 'diagnoseRadio'));
  ?>
  </td>
</tr>
<tr class='part1'>
  <th>Was the person prescribed medication for HF or, if not, did they receive other hemodynamic or procedural support for HF?</th>
  <td>
  <?php 
    echo $form->radio('Review.prescribed_flag', 
                      array(1 => 'Yes&nbsp;&nbsp;', 0 => 'No'), 
                      array('legend' => false, 'id' => 'prescribedRadio'));
  ?>
  </td>
</tr>

<tr class='part2'>
  <td colspan="2"><hr/></td>
</tr>

<tr class="part2">
<th><?php echo "<label for = \"Review.onset_date\">What is the estimated date of onset? (the date of onset is defined as the earliest date of physician diagnosis, presence of objective evidence suggesting heart failure, or new prescription of medication for HF)</label>";?></th>
  <td>
  <?php
    echo $form->input("Review.onset_date", array('label' => '', 
                                                'minYear' => '1985',
                                                'maxYear' => date('Y')));
  ?>
  </td>
</tr>

<tr id='congestion' class='part2'>
  <th>Are there signs mentioned of volume overload or possible volume overload/congestion in the CXR?</th>
  <td>
  <?php 
    echo $form->select('Review.congestion', $congestions, null, array('id' => 'congestionSelect'));
  ?>
  </td>
</tr>

<tr id='echo' class='part2'>
  <th>Was an echocardiogram performed?</th>
  <td>
  <?php 
    echo $form->radio('Review.echo_flag', 
                      array(1 => 'Yes&nbsp;&nbsp;', 0 => 'No'), 
                      array('legend' => false, 'id' => 'echoRadio'));
  ?>
  </td>
</tr>

<tr id='lvef' class='echoyes'>
  <th>If yes, what is the LVEF on the first available echo following HF diagnosis (if no echo within 1 year after HF diagnosis can look for echo closest in time prior to HF diagnosis date)?</th>
  <td>
  <?php 
    echo $form->select('Review.lvef', $lvefs, null,
                       array('id' => 'lvefSelect'));
  ?>
  </td>
</tr>

<tr id='lowLvef' class='echoyes'>
  <th>Does the echocardiogram(s) show evidence of LVEF <50?</th>
  <td>
  <?php 
    echo $form->radio('Review.low_lvef_flag', 
                      array(1 => 'Yes&nbsp;&nbsp;', 0 => 'No'), 
                      array('legend' => false, 'id' => 'lowLvefRadio'));
  ?>
  </td>
</tr>

<tr id='wma' class='echoyes'>
  <th>Does the echocardiogram show evidence of wall motion or other structural abnormalities that could explain HF (includes any grade of diastolic dysfunction, LVH, other noted structural abnormality that might be implicated in HF)?</th>
  <td>
  <?php 
    echo $form->radio('Review.wma_flag', 
                      array(1 => 'Yes&nbsp;&nbsp;', 0 => 'No'), 
                      array('legend' => false, 'id' => 'wmaRadio'));
  ?>
  </td>
</tr>

<tr id='dd' class='echoyes'>
  <th>Does the echocardiogram(s) show evidence of diastolic dysfunction, including grade 1 or higher and/or abnormal relaxation?</th>
  <td>
  <?php 
    echo $form->radio('Review.dd_flag', 
                      array(1 => 'Yes&nbsp;&nbsp;', 0 => 'No'), 
                      array('legend' => false, 'id' => 'ddRadio'));
  ?>
  </td>
</tr>

<tr id='msvd' class='echoyes'>
  <th>Does the echocardiogram(s) show evidence of Moderate-severe valve disease?</th>
  <td>
  <?php 
    echo $form->radio('Review.msvd_flag', 
                      array(1 => 'Yes&nbsp;&nbsp;', 0 => 'No'), 
                      array('legend' => false, 'id' => 'msvdRadio'));
  ?>
  </td>
</tr>

<tr class='part3'>
  <td colspan="2"><hr/></td>
</tr>

<tr id='classification' class='part3'>
  <th>What is the HF classification based on LVEF?</th>
  <td>
  <?php 
    echo $form->select('Review.classification', $classifications, null, array('id' => 'classificationSelect'));
  ?>
  </td>
</tr>

<tr id='etiology' class='part3'>
  <th>
    What is/are the likely etiology of HF?
    <br/><em>Mark all that apply</em>
  </th>

  <td>
    <?php 
      echo $form->input('Review.ischemic_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'ischemic_flag', 'div' => false));
    ?>
    Ischemic
    <br/>
    <?php 
      echo $form->input('Review.nonischemic_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'nonischemic_flag', 'div' => false));
    ?>
    Non-ischemic
    <div id='nonischemic'>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_valvular_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'ni_valvular_flag', 'div' => false));
      ?>
      valvular
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_infiltrative_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_infiltrative_flag', 'div' => false));
      ?>
      infiltrative
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_im_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_im_flag', 'div' => false));
      ?>
      inflammatory myocarditis
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_obstructive_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_obstructive_flag', 'div' => false));
      ?>
      obstructive
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_recreational_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_recreational_flag', 'div' => false));
      ?>
      toxic - recreational drugs
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_prescription_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_prescription_flag', 'div' => false));
      ?>
      toxic - prescription drugs
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_hypertensive_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_hypertensive_flag', 'div' => false));
      ?>
      hypertensive
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_renal_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_renal_flag', 'div' => false));
      ?>
      uremic/renal
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_cardiomyopathy_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_cardiomyopathy_flag', 'div' => false));
      ?>
      HIV / AIDS cardiomyopathy (in setting of uncontrolled viremia)
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_covid_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_covid_flag', 'div' => false));
      ?>
      COVID
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_sepsis_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_sepsis_flag', 'div' => false));
      ?>
      Infectious and/or sepsis (OK to select in addition to HIV or COVID if applicable)
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_metabolic_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_metabolic_flag', 'div' => false));
      ?>
      Metabolic/obesity-related
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_pd_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_pd_flag', 'div' => false));
      ?>
      Pericardial disease
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_hypertrophic_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_hypertrophic_flag', 'div' => false));
      ?>
      Hypertrophic cardiomyopathy known or suspected (not LVH but true HCM)
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.ni_other_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ni_other_flag', 'div' => false));
      ?>
      other
      <div id='ni_other'>
        <?php
          echo $form->input("Review.ni_other", 
                  array('label' => '&nbsp;&nbsp;&nbsp;&nbsp;specify:',
                                'id' => 'niOtherInput'));
        ?>
      </div>
    </div>
    <br/>
    <?php 
      echo $form->input('Review.unknown_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'unknown_flag', 'div' => false));
    ?>
    Unknown
  </td>
</tr>

<tr id='presentation' class='part3'>
  <th>
    What is the presentation of heart failure?
    <br/><em>Choose one only based on sx/imaging</em>
  </th>
  <td>
  <?php 
    echo $form->select('Review.presentation', $presentations, null, array('id' => 'presentationSelect'));
  ?>
  </td>
</tr>

<tr id='moreInfo' class='part3'>
  <th>Do you need more information for assessment?</th>
  <td>
  <?php 
    echo $form->radio('Review.more_info_flag', 
                      array(1 => 'Yes&nbsp;&nbsp;', 0 => 'No'), 
                      array('legend' => false, 'id' => 'moreInfoRadio'));
  ?>
  <div id='needMore'>
    Data needed:
    <br/>
    <?php
      echo $form->textarea("Review.need_more", 
              array('id' => 'needMoreInput'));
    ?>
  </div>
  </td>
</tr>


<tr id='submit'>
  <td colspan="2">
  <?php
    echo $form->submit('Submit');
  ?>
  </td>
</tr>
</table>

</div>

<?php
    echo $form->end();
?>
