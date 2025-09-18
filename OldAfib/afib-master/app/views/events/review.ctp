<script type="text/javascript">
  function showAndHide() {
    var $afib = $("input#afib_flag").is(":checked");
    var $aflutter = $("input#aflutter_flag").is(":checked");
    var $afFo = $("input#af_foundonly_flag").is(":checked");
    var $noAf = $("input#no_af_flag").is(":checked");

    var $afibEncounter = $("input#afib_encounter_flag").is(":checked");
    var $afibHistory = $("input#afib_history_flag").is(":checked");
    var $aflutterEncounter = $("input#aflutter_encounter_flag").is(":checked");
    var $aflutterHistory = $("input#aflutter_history_flag").is(":checked");
    var $afTiming = $("select#afTimingSelect").val();

    var $afibDone = $afib && $afibEncounter && $afTiming != '';
    var $aflutterDone = $aflutter && $aflutterEncounter && $afTiming != '';
    var $afFoDone = $afFo && $afTiming != '';

    var $afibHistoryDone = ($afibHistory && !$afibEncounter) || !$afib;
    var $aflutterHistoryDone = ($aflutterHistory && !$aflutterEncounter) 
                               || !$aflutter;
    var $afHistoryDone = !$afFo && ($afib || $aflutter) && $afibHistoryDone &&
                          $aflutterHistoryDone;

    var $acCoronary = $("input#assoc_coronary_flag").is(":checked");
    var $acMi = $("input#assoc_mi_flag").is(":checked");
    var $acHf = $("input#assoc_hf_flag").is(":checked");
    var $acVhd = $("input#assoc_vhd_flag").is(":checked");
    var $acCopd = $("input#assoc_copd_flag").is(":checked");
    var $acStroke = $("input#assoc_stroke_flag").is(":checked");
    var $acInfection = $("input#assoc_infection_flag").is(":checked");
    var $acThoracic = $("input#assoc_thoracic_flag").is(":checked");
    var $acPvd = $("input#assoc_pvd_flag").is(":checked");
    var $acSurgery = $("input#assoc_surgery_flag").is(":checked");
    var $acNone = $("input#assoc_none_flag").is(":checked");

    var $infectionSepsis = 
          $("input#assoc_infection_sepsis_flag").is(":checked");
    var $infectionBacteremia = 
          $("input#assoc_infection_bacteremia_flag").is(":checked");
    var $infectionPneumonia = 
          $("input#assoc_infection_pneumonia_flag").is(":checked");
    var $infectionOther = 
          $("input#assoc_infection_other_flag").is(":checked");
    var $infectionDone = $infectionSepsis || $infectionBacteremia || 
                         $infectionPneumonia || $infectionOther;

    var $thoracicMalignancy = 
          $("input#assoc_thoracic_malignancy_flag").is(":checked");
    var $thoracicMass = 
          $("input#assoc_thoracic_mass_flag").is(":checked");
    var $thoracicPeric = 
          $("input#assoc_thoracic_pericarditis_flag").is(":checked");
    var $thoracicIld = 
          $("input#assoc_thoracic_ild_flag").is(":checked");
    var $thoracicPh = 
          $("input#assoc_thoracic_ph_flag").is(":checked");
    var $thoracicOther = 
          $("input#assoc_thoracic_other_flag").is(":checked");
    var $thoracicDone = $thoracicMalignancy || $thoracicMass || 
                         $thoracicPeric || $thoracicIld || 
                         $thoracicPh || $thoracicOther;

    var $acDone = $acCoronary || $acMi || $acHf || $acVhd || 
                  $acCopd || $acStroke || $infectionDone || 
                  $thoracicDone || $acPvd ||
                  $acSurgery || $acNone;

    var $smokingUse = $("select#tobaccoSelect").val();
    var $heavyAlcohol = $("input#ha_flag").is(":checked");
    var $subOther = $("input#sub_other_flag").is(":checked");
    var $subMar = $("input#sub_other_marijuana_flag").is(":checked");
    var $subMeth = $("input#sub_other_meth_flag").is(":checked");
    var $subCocaine = $("input#sub_other_cocaine_flag").is(":checked");
    var $subOpiate = $("input#sub_other_opiate_flag").is(":checked");
    var $subUnspecified = $("input#sub_other_unspecified_flag").is(":checked");

    var $substanceDone = $smokingUse != '';

    var $secondaryYes = $("input#SecondaryRadio1:radio:checked").val();
    var $secondaryNo = $("input#SecondaryRadio0:radio:checked").val();
    var $secondaryOther = 
          $("input#secondary_other_flag").is(":checked");

    var $echo = $("input#echo_flag").is(":checked");
    var $echoOther = $("input#echo_other_flag").is(":checked");

    var $afType = $("select#afTypeSelect").val();
    var $afPersistent = ($afType == 'persistent');
    
    var $antic = $("input#antic_flag").is(":checked");
    var $anticAlready = $("input#antic_already_flag").is(":checked");
    var $anticPrescribed = $("input#antic_prescribed_flag").is(":checked");

    $("tr#decision").show();
    $("div#afib_when").hide();
    $("div#aflutter_when").hide();
    $("div#af_timing").hide();
    $("tr.associated").hide();
    $("tr.substance").hide();
    $("tr.secondary").hide();
    $("tr.echo").hide();
    $("tr.type").hide();
    $("tr.antic").hide();
    $("tr#submit").hide();

    if ($afib) {
      $("div#afib_when").show();
    }

    if ($aflutter) {
      $("div#aflutter_when").show();
    }

    if (($afib && $afibEncounter) || ($aflutter && $aflutterEncounter) || $afFo)
    {
      $("div#af_timing").show();
    } else {
      $("div#af_timing").hide();
    }

    if ($noAf || $afHistoryDone) {
      $("tr#submit").show();
    }

    if (!$afHistoryDone && ($afibDone || $aflutterDone || $afFoDone)) {
      $("tr.associated").show();
     
      if ($acCoronary) {
        $("div#coronary").show();
      } else {
        $("div#coronary").hide();
      }

      if ($acMi) {
        $("div#mi").show();
      } else {
        $("div#mi").hide();
      }

      if ($acHf) {
        $("div#hf").show();
      } else {
        $("div#hf").hide();
      }

      if ($acVhd) {
        $("div#vhd").show();
      } else {
        $("div#vhd").hide();
      }

      if ($acCopd) {
        $("div#copd").show();
      } else {
        $("div#copd").hide();
      }

      if ($acStroke) {
        $("div#stroke").show();
      } else {
        $("div#stroke").hide();
      }

      if ($acInfection) {
        $("div#infection").show();

        if ($infectionOther) {
          $("div#infection_other").show();
        } else {
          $("div#infection_other").hide();
        }
      } else {
        $("div#infection").hide();
      }

      if ($acThoracic) {
        $("div#thoracic").show();

        if ($thoracicOther) {
          $("div#thoracic_other").show();
        } else {
          $("div#thoracic_other").hide();
        }
      } else {
        $("div#thoracic").hide();
      }

      if ($acDone) {
        $("tr.substance").show();

        if ($heavyAlcohol) {
          $("div#ha").show();
        } else {
          $("div#ha").hide();
        }

        if ($subOther) {
          $("div#substance_other").show();

          if ($subMeth) {
            $("div#meth").show();
          } else {
            $("div#meth").hide();
          }

          if ($subCocaine) {
            $("div#cocaine").show();
          } else {
            $("div#cocaine").hide();
          }

          if ($subOpiate) {
            $("div#opiate").show();
          } else {
            $("div#opiate").hide();
          }
        } else {
          $("div#substance_other").hide();
        }

        if ($substanceDone) {
          $("tr.secondary").show();

	  if ($secondaryYes) {
            $("div#secondaryCond").show();

            if ($secondaryOther) {
              $("div#secondary_other").show();
            } else {
              $("div#secondary_other").hide();
            }
	  } else {
            $("div#secondaryCond").hide();
	  }

	  if ($secondaryYes || $secondaryNo) {
            $("tr.echo").show();
            $("tr.type").show();

            if ($echo) {
              $("div#echo_info").show();
  
              if ($echoOther) {
                $("div#echo_other").show();
              } else {
                $("div#echo_other").hide();
              }
            } else {
              $("div#echo_info").hide();
            }
  
            if ($afType != '') {
              $("tr.antic").show();
              $("tr#submit").show();
  
              if ($antic) {
                $("div#antic_yes").show();
  
                if ($anticAlready) {
                  $("div#antic_already").show();
                } else {
                  $("div#antic_already").hide();
                }
    
                if ($anticPrescribed) {
                  $("div#antic_prescribed").show();
                } else {
                  $("div#antic_prescribed").hide();
                }
              } else {
                $("div#antic_yes").hide();
              }
            } else {
              $("tr.antic").hide();
              $("tr#submit").hide();
            }
          } else {
            $("tr.echo").hide();
            $("tr.type").hide();
	  }
	} else {
	  $("tr.secondary").hide();
        }
      } else {
        $("tr.substance").hide();
      }
    } else {
      $("tr.associated").hide();
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
<tr id='decision'>
  <th><em>Mark all that apply</em></th>
  <td>
    <?php 
      echo $form->input('Review.afib_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'afib_flag', 'div' => false));
    ?>
    A fib
    <div id='afib_when'>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.afib_encounter_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'afib_encounter_flag', 'div' => false));
      ?>
      During this encounter
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.afib_history_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'afib_history_flag', 'div' => false));
      ?>
      History of A fib
    </div>

    <br/>
    <?php 
      echo $form->input('Review.aflutter_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'aflutter_flag', 'div' => false));
    ?>
    A flutter
    <div id='aflutter_when'>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.aflutter_encounter_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'aflutter_encounter_flag', 'div' => false));
      ?>
      During this encounter
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.aflutter_history_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'aflutter_history_flag', 'div' => false));
      ?>
      History of A flutter
    </div>

    <br/>
    <?php 
      echo $form->input('Review.af_foundonly_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'af_foundonly_flag', 'div' => false));
    ?>
    AF found only on pacer, AICD, or Holter, during this encounter

    <br/>
    <?php 
      echo $form->input('Review.no_af_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'no_af_flag', 'div' => false));
    ?>
    No A fib and no A flutter

    <div id='af_timing'>
      <br/>
      AF timing:
      <?php 
        echo $form->select('Review.af_timing', $timings, null, 
                           array('id' => 'afTimingSelect'));
      ?>
    </div>
  </td>
</tr>

<tr class='associated'>
  <td colspan="2"><hr/></td>
</tr>

<tr class='associated'>
  <th>
    Possible Associated Conditions
    <br/><em>Mark all that apply</em>
  </th>

  <td>
    <?php 
      echo $form->input('Review.assoc_coronary_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_coronary_flag', 'div' => false));
    ?>
    coronary artery disease
    <div id='coronary'>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_coronary_angina_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_coronary_angina_flag', 'div' => false));
      ?>
      angina or ACS during current encounter
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_coronary_cabg_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'assoc_coronary_cabg_flag', 'div' => false));
      ?>
      CABG within 30 days prior
    </div>
    <br/>
    <?php 
      echo $form->input('Review.assoc_mi_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_mi_flag', 'div' => false));
    ?>
    MI
    <div id='mi'>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_mi_30_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_mi_30_flag', 'div' => false));
      ?>
      within 30 days prior
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_mi_gt30_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'assoc_mi_gt30_flag', 'div' => false));
      ?>
      &gt; 30 days prior
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_mi_unknown_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'assoc_mi_unknown_flag', 'div' => false));
      ?>
      timing unknown
    </div>
    <br/>
    <?php 
      echo $form->input('Review.assoc_hf_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_hf_flag', 'div' => false));
    ?>
    heart failure
    <div id='hf'>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_hf_exacerbation_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_hf_exacerbation_flag', 'div' => false));
      ?>
      current HF exacerbation
    </div>
    <br/>
    <?php 
      echo $form->input('Review.assoc_vhd_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_vhd_flag', 'div' => false));
    ?>
    valvular heart disease
    <div id='vhd'>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_vhd_surgery_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_vhd_surgery_flag', 'div' => false));
      ?>
      valve surgery within 30 days prior
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_vhd_disease_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'assoc_vhd_disease_flag', 'div' => false));
      ?>
      valve disease related to endocarditis
    </div>
    <br/>
    <?php 
      echo $form->input('Review.assoc_copd_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_copd_flag', 'div' => false));
    ?>
    COPD
    <div id='copd'>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_copd_exacerbation_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_copd_exacerbation_flag', 'div' => false));
      ?>
      current COPD exacerbation
    </div>
    <br/>
    <?php 
      echo $form->input('Review.assoc_stroke_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_stroke_flag', 'div' => false));
    ?>
    ischemic stroke/transient ischemic attack
    <div id='stroke'>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_stroke_current_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_stroke_current_flag', 'div' => false));
      ?>
      during current encounter
    </div>
    <br/>
    <?php 
      echo $form->input('Review.assoc_infection_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_infection_flag', 'div' => false));
    ?>
    infection
    <div id='infection'>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_infection_sepsis_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_infection_sepsis_flag', 'div' => false));
      ?>
      sepsis
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_infection_bacteremia_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'assoc_infection_bacteremia_flag', 'div' => false));
      ?>
      bacteremia/fungemia
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_infection_pneumonia_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'assoc_infection_pneumonia_flag', 'div' => false));
      ?>
      pneumonia
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_infection_other_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'assoc_infection_other_flag', 'div' => false));
      ?>
      other infection
      <div id='infection_other'>
        <?php
          echo $form->input("Review.assoc_infection_other", array('label' => 'Specify:',
                                'id' => 'assocInfectionOtherInput'));
        ?>
      </div>
    </div>
    <br/>
    <?php 
      echo $form->input('Review.assoc_thoracic_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_thoracic_flag', 'div' => false));
    ?>
    thoracic disease
    <div id='thoracic'>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_thoracic_malignancy_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_thoracic_malignancy_flag', 'div' => false));
      ?>
      malignancy
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_thoracic_mass_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'assoc_thoracic_mass_flag', 'div' => false));
      ?>
      mass of unknown significance
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_thoracic_pericarditis_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'assoc_thoracic_pericarditis_flag', 'div' => false));
      ?>
      pericarditis
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_thoracic_ild_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'assoc_thoracic_ild_flag', 'div' => false));
      ?>
      interstitial lung disease
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_thoracic_ph_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'assoc_thoracic_ph_flag', 'div' => false));
      ?>
      pulmonary hypertension
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.assoc_thoracic_other_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'assoc_thoracic_other_flag', 'div' => false));
      ?>
      other
      <div id='thoracic_other'>
        <?php
          echo $form->input("Review.assoc_thoracic_other", array('label' => 'Specify:',
                                'id' => 'assocThoracicOtherInput'));
        ?>
      </div>
    </div>
    <br/>
    <?php 
      echo $form->input('Review.assoc_pvd_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_pvd_flag', 'div' => false));
    ?>
    peripheral vascular disease
    <br/>
    <?php 
      echo $form->input('Review.assoc_surgery_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_surgery_flag', 'div' => false));
    ?>
    surgery within 30 days prior, involving general anesthesia
    <br/>
    <?php 
      echo $form->input('Review.assoc_none_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'assoc_none_flag', 'div' => false));
    ?>
    none
  </td>
</tr>

<tr class='substance'>
  <td colspan="2"><hr/></td>
</tr>

<tr class='substance'>
  <th>Substance use</th>
  <td>
    tobacco:
    <?php 
      echo $form->select('Review.tobacco', $smokingUses, null, 
                         array('id' => 'tobaccoSelect'));
    ?>
    <br/>
    <?php 
      echo $form->input('Review.ha_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'ha_flag', 'div' => false));
    ?>
    heavy alcohol
     <div id='ha'>
       &nbsp;&nbsp;
       <?php
         echo $form->input('Review.ha_intox_flag',
                      array('type' => 'checkbox', 'label' => '', 
                           'id' => 'ha_intox_flag', 'div' => false));
       ?>
       intoxicated at presentation with AF
     </div>
    <br/>
    <?php 
      echo $form->input('Review.sub_other_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'sub_other_flag', 'div' => false));
    ?>
    other substances
    <div id="substance_other">
      &nbsp;&nbsp;
      <em>Mark all that apply</em>
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.sub_other_marijuana_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'sub_other_marijuana_flag', 'div' => false));
      ?>
      marijuana
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.sub_other_meth_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'sub_other_meth_flag', 'div' => false));
      ?>
      methamphetamine/crystal
      <div id='meth'>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
          echo $form->input('Review.sub_other_meth_intox_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'sub_other_meth_intox_flag', 'div' => false));
        ?>
        intoxicated at presentation with AF
      </div>
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.sub_other_cocaine_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'sub_other_cocaine_flag', 'div' => false));
      ?>
      cocaine/crack
      <div id='cocaine'>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
          echo $form->input('Review.sub_other_cocaine_intox_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'sub_other_cocaine_intox_flag', 'div' => false));
        ?>
        intoxicated at presentation with AF
      </div>
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.sub_other_opiate_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'sub_other_opiate_flag', 'div' => false));
      ?>
      opiate/heroin
      <div id='opiate'>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
          echo $form->input('Review.sub_other_opiate_intox_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'sub_other_opiate_intox_flag', 'div' => false));
        ?>
        intoxicated at presentation with AF
      </div>
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.sub_other_unspecified_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'sub_other_unspecified_flag', 'div' => false));
      ?>
      other substance/not specified
    </div>
  </td>
</tr>

<tr class='secondary'>
  <td colspan="2"><hr/></td>
</tr>

<tr class='secondary'>
  <th>AF occurred secondary to an acute condition?</th>
  <td>
    <?php 
      echo $form->radio('Review.af_secondary_flag', 
		        array(1 => 'Yes&nbsp;&nbsp;', 0 => 'No'),
		        array('legend' => false, 'id' => 'secondaryRadio'));
    ?>
    <div id="secondaryCond">
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.secondary_infection_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'secondary_infection_flag', 'div' => false));
      ?>
      infection/sepsis
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.secondary_alcohol_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'secondary_alcohol_flag', 'div' => false));
      ?>
      alcohol or drug overdose
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.secondary_thoracic_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'secondary_thoracic_flag', 'div' => false));
      ?>
      thoracic disease
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.secondary_postop_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'secondary_postop_flag', 'div' => false));
      ?>
      post-op
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.secondary_other_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'secondary_other_flag', 'div' => false));
      ?>
      other
      <div id='secondary_other'>
        <?php
          echo $form->input("Review.secondary_other", 
                  array('label' => '&nbsp;&nbsp;&nbsp;&nbsp;specify:',
                                'id' => 'secondaryOtherInput'));
        ?>
      </div>
    </div>
  </td>
</tr>

<tr class='echo'>
  <td colspan="2"><hr/></td>
</tr>

<tr class='echo'>
  <th>Echocardiogram</th>
  <td>
    <?php 
      echo $form->input('Review.echo_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'echo_flag', 'div' => false));
    ?>
    Echocardiogram available?
    <div id="echo_info">
      &nbsp;&nbsp;
      <em>Mark all that apply</em>
      &nbsp;&nbsp;
      <?php
        echo $form->input("Review.echo_ef_percent", 
                          array('label' => '&nbsp;&nbsp;&nbsp;LV ejection fraction (%)',
                              'id' => 'echoEfPercentInput',
                              'maxlength' => 4,
                              'size' => 4)) . 
             $form->input("Review.echo_ef_text", 
                      array('label' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(text)',
                            'id' => 'echoEfTextInput')) ;
      ?>
      <?php
        echo $form->input("Review.echo_lae_dimension_cm", 
               array('label'=>'&nbsp;&nbsp;&nbsp;LA dimension (cm)',
                     'id' => 'echoLaeDCmInput',
                     'maxlength' => 5,
                     'size' => 5)) .
             $form->input("Review.echo_lae_dimension_text", 
               array('label'=>'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(text)',
                     'id' => 'echoLaeDTextInput')) .
             $form->input("Review.echo_lae_volume", 
               array('label' => '&nbsp;&nbsp;&nbsp;LA volume index (ml/m<sub>2</sub>)',
                     'id' => 'echoLaevInput'));
      ?>
      <?php
        echo $form->input("Review.echo_valve_disease", 
               array('label' => '&nbsp;&nbsp;&nbsp;valve disease (specify)',
                     'id' => 'echoVdInput'));
      ?>
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.echo_lvseg_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'echo_lvseg_flag', 'div' => false));
      ?>
      LV segmental wall motion abnormality
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.echo_lvgh_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'echo_lvgh_flag', 'div' => false));
      ?>
      LV global hypokinesis
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.echo_lvdd_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'echo_lvdd_flag', 'div' => false));
      ?>
      LV diastolic dysfunction
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.echo_lvh_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'echo_lvh_flag', 'div' => false));
      ?>
      LV hypertrophy
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.echo_lve_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'echo_lve_flag', 'div' => false));
      ?>
      LV enlargement
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.echo_rv_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'echo_rv_flag', 'div' => false));
      ?>
      RV enlargement or depressed systolic function
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.echo_elevatedpressure_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'echo_elevatedpressure_flag', 'div' => false));
      ?>
      elevated pulmonary artery pressure
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.echo_other_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'echo_other_flag', 'div' => false));
      ?>
      other
      <div id='echo_other'>
        <?php
          echo $form->input("Review.echo_other", 
                  array('label' => '&nbsp;&nbsp;&nbsp;&nbsp;specify:',
                                'id' => 'echoOtherInput'));
        ?>
      </div>
    </div>
  </td>
</tr>

<tr class='type'>
  <td colspan="2"><hr/></td>
</tr>

<tr class='type'>
  <th>AF duration subtype</th>
  <td>
    On this occasion, was AF:
    <br/>
    <?php 
      echo $form->select('Review.af_type', $afTypes, null, 
                         array('id' => 'afTypeSelect'));
    ?>
  </td>
</tr>

<tr class='antic'>
  <td colspan="2"><hr/></td>
</tr>

<tr class='antic'>
  <th>Anticoagulant</th>
  <td>
    <?php 
      echo $form->input('Review.antic_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'antic_flag', 'div' => false));
    ?>
    Anticoagulated?
    <div id="antic_yes">
      <em>Mark all that apply</em>
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.antic_already_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'antic_already_flag', 'div' => false));
      ?>
      already on anticoagulant at time of this AF diagnosis
      <div id='antic_already'>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <em>Mark all that apply</em>
        <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
          echo $form->input('Review.antic_already_warfarin_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'antic_already_warfarin_flag', 'div' => false));
        ?>
        warfarin
        <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
          echo $form->input('Review.antic_already_noac_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'antic_already_noac_flag', 'div' => false));
        ?>
        NOAC
        <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
          echo $form->input('Review.antic_already_aspirin_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'antic_already_aspirin_flag', 'div' => false));
        ?>
        aspirin
        <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
          echo $form->input('Review.antic_already_other_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'antic_already_other_flag', 'div' => false));
        ?>
        other
        <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
          echo $form->input('Review.antic_already_unknown_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'antic_already_unknown_flag', 'div' => false));
        ?>
        unknown type
      </div>
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.antic_prescribed_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'antic_prescribed_flag', 'div' => false));
      ?>
      anticoagulant prescribed within 1 month after this AF diagnosis
      <div id='antic_prescribed'>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <em>Mark all that apply</em>
        <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
          echo $form->input('Review.antic_prescribed_warfarin_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'antic_prescribed_warfarin_flag', 'div' => false));
        ?>
        warfarin
        <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
          echo $form->input('Review.antic_prescribed_noac_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'antic_prescribed_noac_flag', 'div' => false));
        ?>
        NOAC
        <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
          echo $form->input('Review.antic_prescribed_aspirin_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'antic_prescribed_aspirin_flag', 'div' => false));
        ?>
        aspirin
        <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
          echo $form->input('Review.antic_prescribed_other_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'antic_prescribed_other_flag', 'div' => false));
        ?>
        other
        <br/>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
          echo $form->input('Review.antic_prescribed_unknown_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'antic_prescribed_unknown_flag', 'div' => false));
        ?>
        unknown type
      </div>
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
