<script type="text/javascript">
  function showAndHide() {
    var $peflag = $("input#pe_flag").is(":checked");
    var $dvtflag = $("input#dvt_flag").is(":checked");
    var $catflag = $("input#cat_flag").is(":checked");
    var $noVte = $("input#no_vte_flag").is(":checked");
    var $catSubtype = $("select#catSubtypeSelect").val();
    var $pedp = $("select#peDpSelect").val();
    var $dvtdp = $("select#dvtDpSelect").val();
    var $catdp = $("select#catDpSelect").val();
    var $peType = $("select#peTypeSelect").val();
    var $dvtType = $("select#dvtTypeSelect").val();
    var $catType = $("select#catTypeSelect").val();
    var $peDone = $peflag && $pedp != '' && $peType != '';
    var $dvtDone = $dvtflag && $dvtdp != '' && $dvtType != '';
    var $catDone = $catflag && $catSubtype != '' && $catdp != '' && 
                   $catType != '';
    var $peMain = $("input#pe_main_flag").is(":checked");
    var $peLobar = $("input#pe_lobar_flag").is(":checked");
    var $peSeg = $("input#pe_segmental_flag").is(":checked");
    var $peSubseg = $("input#pe_subsegmental_flag").is(":checked");
    var $peUnknown = $("input#pe_unknown_flag").is(":checked");
    var $peLocationDone = $peMain || $peLobar || $peSeg || $peSubseg || 
                          $peUnknown;

    var $dvtUe = $("input#dvt_ue_flag").is(":checked");
    var $dvtLe = $("input#dvt_le_flag").is(":checked");
    var $dvtOther = $("input#dvt_other_flag").is(":checked");
    var $dvtUnknown = $("input#dvt_unknown_flag").is(":checked");

    var $dvtLeProx = $("input#dvt_le_proximal_flag").is(":checked");
    var $dvtLeDist = $("input#dvt_le_distal_flag").is(":checked");
    var $dvtLeUnknown = $("input#dvt_le_unknown_flag").is(":checked");

    var $dvtOtherNeck = $("input#dvt_other_neck_flag").is(":checked");
    var $dvtOtherVc = $("input#dvt_other_vc_flag").is(":checked");
    var $dvtOtherAp = $("input#dvt_other_ap_flag").is(":checked");
    var $dvtOtherPs = $("input#dvt_other_ps_flag").is(":checked");
    var $dvtOtherIc = $("input#dvt_other_ic_flag").is(":checked");
    var $dvtOtherOther = $("input#dvt_other_other_flag").is(":checked");
    var $dvtOtherUnknown = $("input#dvt_other_unknown_flag").is(":checked");

    var $dvtJugular = $("input#dvt_other_neck_jugular_flag").is(":checked");
    var $dvtSub = $("input#dvt_other_neck_subclavian_flag").is(":checked");
    var $dvtBrach = $("input#dvt_other_neck_brach_flag").is(":checked");
    var $dvtNUnknown = $("input#dvt_other_neck_unknown_flag").is(":checked");
    var $dvtRenal = $("input#dvt_other_ap_renal_flag").is(":checked");
    var $dvtAPHep = $("input#dvt_other_ap_hepatic_flag").is(":checked");
    var $dvtPelvic = $("input#dvt_other_ap_pelvic_flag").is(":checked");
    var $dvtIliac = $("input#dvt_other_ap_iliac_flag").is(":checked");
    var $dvtAPUnknown = $("input#dvt_other_ap_unknown_flag").is(":checked");
    var $dvtPSHep = $("input#dvt_other_ps_hepatic_flag").is(":checked");
    var $dvtSplenic = $("input#dvt_other_ps_splenic_flag").is(":checked");
    var $dvtMes = $("input#dvt_other_ps_mesenteric_flag").is(":checked");
    var $dvtPSUnknown = $("input#dvt_other_ps_unknown_flag").is(":checked");
    var $dvtSST = $("input#dvt_other_ic_sst_flag").is(":checked");
    var $dvtTST = $("input#dvt_other_ic_tst_flag").is(":checked");
    var $dvtRVT = $("input#dvt_other_ic_rvt_flag").is(":checked");
    var $dvtICUnknown = $("input#dvt_other_ic_unknown_flag").is(":checked");
    var $dvtLocationDone = $dvtUe || $dvtLeProx || $dvtLeDist || 
                           $dvtLeUnknown || $dvtJugular || $dvtSub ||
                           $dvtBrach || $dvtNUnknown || $dvtOtherVc ||
                           $dvtRenal || $dvtAPHep || $dvtPelvic ||
                           $dvtIliac || $dvtAPUnknown || $dvtPSHep ||
                           $dvtSplenic || $dvtMes || $dvtPSUnknown ||
                           $dvtSST || $dvtTST || $dvtRVT ||
                           $dvtICUnknown || $dvtOtherOther || 
                           $dvtOtherUnknown || $dvtUnknown;

    var $infection = $("input#cc_infection_flag").is(":checked");
    var $infectionOther = $("input#cc_infection_other_flag").is(":checked");
    var $ivDrug = $("input#cc_ivdrug_flag").is(":checked");

    var $ccMalignancy = $("input#cc_malignancy_flag").is(":checked");
    var $ccChemo = $("input#cc_chemo_flag").is(":checked");
    var $ccHF = $("input#cc_heartfailure_flag").is(":checked");
    var $ccNS = $("input#cc_ns_flag").is(":checked");
    var $ccDialysis = $("input#cc_dialysis_flag").is(":checked");
    var $ccHosp = $("input#cc_hosp_flag").is(":checked");
    var $ccMT = $("input#cc_mt_flag").is(":checked");
    var $ccImmob = $("input#cc_immob_flag").is(":checked");
    var $ccLongride = $("input#cc_longride_flag").is(":checked");
    var $ccSurgery = $("input#cc_surgery_flag").is(":checked");
    var $ccPneu = $("input#cc_infection_pneumonia_flag").is(":checked");
    var $ccSepsis = $("input#cc_infection_sepsis_flag").is(":checked");
    var $ccUti = $("input#cc_infection_uti_flag").is(":checked");
    var $ccEndoc = $("input#cc_infection_endocarditis_flag").is(":checked");
    var $ccOsteom = $("input#cc_infection_osteomyelitis_flag").is(":checked");
    var $ccMenin = $("input#cc_infection_meningitis_flag").is(":checked");
    var $ccCellu = $("input#cc_infection_cellulitis_flag").is(":checked");
    var $ccCovid = $("input#cc_infection_covid_flag").is(":checked");
    var $ccIOther = $("input#cc_infection_other_flag").is(":checked");
    var $ccTransf = $("input#cc_transfusion_flag").is(":checked");
    var $ccInherited = $("input#cc_inherited_flag").is(":checked");
    var $ccIVDrug = $("select#ivDrugSelect").val();
    var $ccCopd = $("input#cc_copd_flag").is(":checked");
    var $ccPh = $("input#cc_ph_flag").is(":checked");
    var $ccSteroid = $("input#cc_steroid_flag").is(":checked");
    var $ccPreg = $("input#cc_pregnancy_flag").is(":checked");
    var $ccOther = $("input#cc_other_flag").is(":checked");
    var $ccUnknown = $("input#cc_unknown_flag").is(":checked");
    var $ccNone = $("input#cc_none_flag").is(":checked");
    var $ccDone = $ccMalignancy || $ccChemo || 
                           $ccHF || $ccNS || $ccDialysis ||
                           $ccHosp || $ccMT || $ccImmob ||
                           $ccLongride || $ccSurgery || $ccPneu ||
                           $ccSepsis || $ccUti || $ccEndoc ||
                           $ccOsteom || $ccMenin || $ccCellu || $ccCovid ||
                           $ccIOther || $ccTransf || $ccInherited ||
                           $ccIVDrug != '' || $ccCopd || $ccPh ||
                           $ccSteroid || $ccPreg || $ccOther ||
                           $ccUnknown || $ccNone;

    var $historyPe = $("input#vtehistory_pe_flag").is(":checked");
    var $historyDvt = $("input#vtehistory_dvt_flag").is(":checked");
    var $historyUT = $("input#vtehistory_unknowntype_flag").is(":checked");
    var $historyNone = $("input#vtehistory_none_flag").is(":checked");
    var $historyUnknown = $("input#vtehistory_unknown_flag").is(":checked");
    var $smokingUse = $("select#smokingUseSelect").val();
    var $fhistory = $("select#familyHistorySelect").val();

    var $mcDone = $smokingUse != '' && $fhistory != '' && 
                  ($historyPe || $historyDvt || $historyUT || $historyNone ||
                   $historyUnknown);

    var $managementInfo = $("input#ManagementInfoRadio1:radio:checked").val();
    var $managementInfoN = $("input#ManagementInfoRadio0:radio:checked").val();
    var $mAt = $("select#managementAtSelect").val();
    var $mHosp = $("select#managementHospSelect").val();
    var $mVcf = $("select#managementVcfSelect").val();
    var $mTT = $("select#managementTtSelect").val();
    var $mThromb = $("select#managementThrombectomySelect").val();
    var $mMA = $("select#managementManagedasSelect").val();

    var $managementDone = $managementInfoN ||
           $managementInfo && $mAt != '' && $mHosp != '' && $mVcf != '' &&
           $mTT != '' && $mThromb != '' && $mMA != '';

    $("tr#types").show();
    $("div#pe_more").hide();
    $("div#dvt_more").hide();
    $("div#cat_subtype").hide();
    $("div#cat_more").hide();
    $("tr.location").hide();
    $("tr.contcond").hide();
    $("tr.mc").hide();
    $("tr.management").hide();
    $("tr#submit").hide();

    if ($peflag) {
      $("div#pe_more").show();

      if ($pedp != '') {
        $("div#pe_type").show();
      } else {
        $("div#pe_type").hide();
      }
    }

    if ($dvtflag) {
      $("div#dvt_more").show();

      if ($dvtdp != '') {
        $("div#dvt_type").show();
      } else {
        $("div#dvt_type").hide();
      }
    }

    if ($catflag) {
      $("div#cat_subtype").show();

      if ($catSubtype != '') {
        $("div#cat_more").show();

        if ($catdp != '') {
          $("div#cat_type").show();
        } else {
          $("div#cat_type").hide();
        }
      } else {
        $("div#cat_more").hide();
      }
    }

    if ($noVte) {
      $("tr#submit").show();
    }

    if ($peDone || $dvtDone || $catDone) {
      $("tr.location").show();
     
      if ($peDone) {
        $("div#pe_location").show();
      } else {
        $("div#pe_location").hide();
      }

      if ($dvtDone || $catDone) {
        $("div#dvt_location").show();

        if ($dvtLe) {
          $("div#dvt_le_location").show();
        } else {
          $("div#dvt_le_location").hide();
        }

        if ($dvtOther) {
          $("div#dvt_other_location").show();

          if ($dvtOtherNeck) {
            $("div#dvt_nc_location").show();
          } else {
            $("div#dvt_nc_location").hide();
          }

          if ($dvtOtherAp) {
            $("div#dvt_ap_location").show();
          } else {
            $("div#dvt_ap_location").hide();
          }

          if ($dvtOtherPs) {
            $("div#dvt_ps_location").show();
          } else {
            $("div#dvt_ps_location").hide();
          }

          if ($dvtOtherIc) {
            $("div#dvt_ic_location").show();
          } else {
            $("div#dvt_ic_location").hide();
          }

          if ($dvtOtherOther) {
            $("div#dvt_other_other_location").show();
          } else {
            $("div#dvt_other_other_location").hide();
          }
        } else {
          $("div#dvt_other_location").hide();
        }
      } else {
        $("div#dvt_location").hide();
      }
    }

    if (($peDone && $peLocationDone) || 
        (($dvtDone || $catDone) && $dvtLocationDone)) 
    {
      $("tr.contcond").show();

      if ($infection) {  
        $("div#cc_infection").show();

        if ($infectionOther) {  
          $("div#cc_infection_other").show();
        } else {
          $("div#cc_infection_other").hide();
        }
      } else {
        $("div#cc_infection").hide();
      }

      if ($ivDrug) {  
        $("div#iv_drug").show();
      } else {
        $("div#iv_drug").hide();
      }

      if ($ccOther) {  
        $("div#cc_other").show();
      } else {
        $("div#cc_other").hide();
      }

      if ($ccDone) {
        $("tr.mc").show();

        if ($mcDone) {
          $("tr.management").show();

          if ($managementInfo) {  
            $("div#management_more").show();
          } else {
            $("div#management_more").hide();
          }

          if ($managementDone) {
            $("tr#submit").show();
          } else {
            $("tr#submit").hide();
          }
        } else {
          $("tr.management").hide();
        }
      } else {
        $("tr.mc").hide();
      }
    } else {
      $("tr.contcond").hide();
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

<h1>Review event: <?php echo PROJECT_NAME == 'MI' ? 'MI' . (1000 + $eventId) : 
                                                    $eventId; ?></h1>
                             
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
        $anchor = PROJECT_NAME == 'MI' ? 
            "Download charts for MI" . (1000 + $eventId) :
            "Download charts for Event " . $eventId;
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
<tr id='types'>
  <th>Please mark any VTE events.<br/><em>Mark all that apply</em></th>
  <td>
    <?php 
      echo $form->input('Review.pe_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'pe_flag', 'div' => false));
    ?>
    PE
    <div id='pe_more'>
      Definite or Probable?
      <br/>
      <?php 
        echo $form->select('Review.pe_dp', $dps, null, 
                           array('id' => 'peDpSelect'));
      ?>

      <div id='pe_type'>
      Acute/Chronic/Unspecified?
      <br/>
      <?php 
        echo $form->select('Review.pe_type', $types, null, 
                           array('id' => 'peTypeSelect'));
      ?>
      </div>
    </div>

    <br/>
    <?php 
      echo $form->input('Review.dvt_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'dvt_flag', 'div' => false));
    ?>
    DVT
    <div id='dvt_more'>
      Definite or Probable?
      <br/>
      <?php 
        echo $form->select('Review.dvt_dp', $dps, null, 
                           array('id' => 'dvtDpSelect'));
      ?>

      <div id='dvt_type'>
      Acute/Chronic/Unspecified?
      <br/>
      <?php 
        echo $form->select('Review.dvt_type', $types, null, 
                           array('id' => 'dvtTypeSelect'));
      ?>
      </div>
    </div>

    <br/>
    <?php 
      echo $form->input('Review.cat_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cat_flag', 'div' => false));
    ?>
    Catheter-induced thrombosis
    <div id='cat_subtype'>
      Type:
      <br/>
      <?php 
        echo $form->select('Review.cat_subtype', $catSubtypes, null, 
                           array('id' => 'catSubtypeSelect'));
      ?>
      <br/>
      <div id='cat_more'>
        Definite or Probable?
        <br/>
        <?php 
          echo $form->select('Review.cat_dp', $dps, null, 
                             array('id' => 'catDpSelect'));
        ?>

        <div id='cat_type'>
        Acute/Chronic/Unspecified?
        <br/>
        <?php 
          echo $form->select('Review.cat_type', $types, null, 
                             array('id' => 'catTypeSelect'));
        ?>
        </div>
      </div>
    </div>

    <br/>
    <?php 
      echo $form->input('Review.no_vte_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'no_vte_flag', 'div' => false));
    ?>
    No VTE
  </td>
</tr>

<tr class='location'>
  <td colspan="2"><hr/></td>
</tr>

<tr class='location'>
  <th>
    Location
    <br/><em>Mark all that apply</em>
  </th>

  <td>
    <div id='pe_location'>
      <br/>
      Please identify the location of the PE.
      <br/>
      <?php 
        echo $form->input('Review.pe_main_flag',
                        array('type' => 'checkbox', 'label' => '', 
                              'id' => 'pe_main_flag', 'div' => false));
      ?>
      Main pulmonary artery(ies)
      <br/>
      <?php 
        echo $form->input('Review.pe_lobar_flag',
                        array('type' => 'checkbox', 'label' => '', 
                              'id' => 'pe_lobar_flag', 'div' => false));
      ?>
      Lobar
      <br/>
      <?php 
        echo $form->input('Review.pe_segmental_flag',
                        array('type' => 'checkbox', 'label' => '', 
                              'id' => 'pe_segmental_flag', 'div' => false));
      ?>
      Segmental
      <br/>
      <?php 
        echo $form->input('Review.pe_subsegmental_flag',
                        array('type' => 'checkbox', 'label' => '', 
                              'id' => 'pe_subsegmental_flag', 'div' => false));
      ?>
      Sub-segmental
      <br/>
      <?php 
        echo $form->input('Review.pe_unknown_flag',
                        array('type' => 'checkbox', 'label' => '', 
                              'id' => 'pe_unknown_flag', 'div' => false));
      ?>
      Unknown
    </div>
    <div id='dvt_location'>
      <br/>
      Please identify the location of the DVT.
      <br/>
      <?php 
        echo $form->input('Review.dvt_ue_flag',
                        array('type' => 'checkbox', 'label' => '', 
                              'id' => 'dvt_ue_flag', 'div' => false));
      ?>
      UE
      <br/>
      <?php 
        echo $form->input('Review.dvt_le_flag',
                        array('type' => 'checkbox', 'label' => '', 
                              'id' => 'dvt_le_flag', 'div' => false));
      ?>
      LE
      <div id='dvt_le_location'>
        &nbsp;&nbsp;
        <?php 
          echo $form->input('Review.dvt_le_proximal_flag',
                        array('type' => 'checkbox', 'label' => '', 
                              'id' => 'dvt_le_proximal_flag', 'div' => false));
        ?>
        Proximal (popliteal, femoral, iliac)
        <br/>
        &nbsp;&nbsp;
        <?php 
          echo $form->input('Review.dvt_le_distal_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'dvt_le_distal_flag', 'div' => false));
        ?>
        Distal
        <br/>
        &nbsp;&nbsp;
        <?php 
          echo $form->input('Review.dvt_le_unknown_flag',
                       array('type' => 'checkbox', 'label' => '', 
                             'id' => 'dvt_le_unknown_flag', 'div' => false));
        ?>
        Unknown
      </div>
      <br/>
      <?php 
        echo $form->input('Review.dvt_other_flag',
                        array('type' => 'checkbox', 'label' => '', 
                              'id' => 'dvt_other_flag', 'div' => false));
      ?>
      Other location
      <div id='dvt_other_location'>
        &nbsp;&nbsp;
        <?php 
          echo $form->input('Review.dvt_other_neck_flag',
                          array('type' => 'checkbox', 'label' => '', 
                                'id' => 'dvt_other_neck_flag', 'div' => false));
        ?>
        Neck/Chest
        <div id='dvt_nc_location'>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_neck_jugular_flag',
                          array('type' => 'checkbox', 'label' => '', 
                                'id' => 'dvt_other_neck_jugular_flag', 'div' => false));
          ?>
          Jugular
          <br/>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_neck_subclavian_flag',
                         array('type' => 'checkbox', 'label' => '', 
                               'id' => 'dvt_other_neck_subclavian_flag', 'div' => false));
          ?>
          Subclavian
          <br/>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_neck_brach_flag',
                         array('type' => 'checkbox', 'label' => '', 
                               'id' => 'dvt_other_neck_brach_flag', 'div' => false));
          ?>
          Brachiocephalic (innominate)
          <br/>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_neck_unknown_flag',
                         array('type' => 'checkbox', 'label' => '', 
                               'id' => 'dvt_other_neck_unknown_flag', 'div' => false));
          ?>
          Unknown
        </div>
        <br/>
        &nbsp;&nbsp;
        <?php 
          echo $form->input('Review.dvt_other_vc_flag',
                          array('type' => 'checkbox', 'label' => '', 
                                'id' => 'dvt_other_vc_flag', 'div' => false));
        ?>
        Vena cava (superior or inferior)
        <br/>
        &nbsp;&nbsp;
        <?php 
          echo $form->input('Review.dvt_other_ap_flag',
                          array('type' => 'checkbox', 'label' => '', 
                                'id' => 'dvt_other_ap_flag', 'div' => false));
        ?>
        Abdomen/pelvis
        <div id='dvt_ap_location'>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_ap_renal_flag',
                          array('type' => 'checkbox', 'label' => '', 
                                'id' => 'dvt_other_ap_renal_flag', 'div' => false));
          ?>
          Renal
          <br/>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_ap_hepatic_flag',
                         array('type' => 'checkbox', 'label' => '', 
                               'id' => 'dvt_other_ap_hepatic_flag', 'div' => false));
          ?>
          Hepatic
          <br/>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_ap_pelvic_flag',
                         array('type' => 'checkbox', 'label' => '', 
                               'id' => 'dvt_other_ap_pelvic_flag', 'div' => false));
          ?>
          Pelvic
          <br/>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_ap_iliac_flag',
                         array('type' => 'checkbox', 'label' => '', 
                               'id' => 'dvt_other_ap_iliac_flag', 'div' => false));
          ?>
          Iliac
          <br/>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_ap_unknown_flag',
                         array('type' => 'checkbox', 'label' => '', 
                               'id' => 'dvt_other_ap_unknown_flag', 'div' => false));
          ?>
          Unknown
        </div>
        <br/>
        &nbsp;&nbsp;
        <?php 
          echo $form->input('Review.dvt_other_ps_flag',
                          array('type' => 'checkbox', 'label' => '', 
                                'id' => 'dvt_other_ps_flag', 'div' => false));
        ?>
        Portal system
        <div id='dvt_ps_location'>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_ps_hepatic_flag',
                          array('type' => 'checkbox', 'label' => '', 
                                'id' => 'dvt_other_ps_hepatic_flag', 'div' => false));
          ?>
          Hepatic portal
          <br/>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_ps_splenic_flag',
                         array('type' => 'checkbox', 'label' => '', 
                               'id' => 'dvt_other_ps_splenic_flag', 'div' => false));
          ?>
          Splenic
          <br/>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_ps_mesenteric_flag',
                         array('type' => 'checkbox', 'label' => '', 
                               'id' => 'dvt_other_ps_mesenteric_flag', 'div' => false));
          ?>
          Mesenteric
          <br/>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_ps_unknown_flag',
                         array('type' => 'checkbox', 'label' => '', 
                               'id' => 'dvt_other_ps_unknown_flag', 'div' => false));
          ?>
          Unknown
        </div>
        <br/>
        &nbsp;&nbsp;
        <?php 
          echo $form->input('Review.dvt_other_ic_flag',
                          array('type' => 'checkbox', 'label' => '', 
                                'id' => 'dvt_other_ic_flag', 'div' => false));
        ?>
        Intercranial
        <div id='dvt_ic_location'>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_ic_sst_flag',
                          array('type' => 'checkbox', 'label' => '', 
                                'id' => 'dvt_other_ic_sst_flag', 'div' => false));
          ?>
          Sagittal sinus thrombosis
          <br/>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_ic_tst_flag',
                         array('type' => 'checkbox', 'label' => '', 
                               'id' => 'dvt_other_ic_tst_flag', 'div' => false));
          ?>
          Transverse sinus thrombosis
          <br/>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_ic_rvt_flag',
                         array('type' => 'checkbox', 'label' => '', 
                               'id' => 'dvt_other_ic_rvt_flag', 'div' => false));
          ?>
          Retinal vein thrombosis
          <br/>
          &nbsp;&nbsp;&nbsp;&nbsp;
          <?php 
            echo $form->input('Review.dvt_other_ic_unknown_flag',
                         array('type' => 'checkbox', 'label' => '', 
                               'id' => 'dvt_other_ic_unknown_flag', 'div' => false));
          ?>
          Unknown
        </div>
        <br/>
        &nbsp;&nbsp;
        <?php 
          echo $form->input('Review.dvt_other_other_flag',
                          array('type' => 'checkbox', 'label' => '', 
                                'id' => 'dvt_other_other_flag', 'div' => false));
        ?>
        Other
        <div id='dvt_other_other_location'>
          <?php
            echo $form->input("Review.dvt_other", array('label' => 'Specify:',
                                                  'id' => 'dvtOtherInput'));
          ?>
        </div>
        <br/>
        &nbsp;&nbsp;
        <?php 
          echo $form->input('Review.dvt_other_unknown_flag',
                          array('type' => 'checkbox', 'label' => '', 
                                'id' => 'dvt_other_unknown_flag', 'div' => false));
        ?>
        Unknown
      </div>
      <br/>
      <?php 
        echo $form->input('Review.dvt_unknown_flag',
                        array('type' => 'checkbox', 'label' => '', 
                              'id' => 'dvt_unknown_flag', 'div' => false));
      ?>
      Unknown
    </div>
  </td>
</tr>

<tr class='contcond'>
  <td colspan="2"><hr/></td>
</tr>

<tr class='contcond'>
  <th>
    Possible contributing conditions
    <br/><em>Mark all that apply</em>
  </th>

  <td>
    <br/>
    <?php 
      echo $form->input('Review.cc_malignancy_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_malignancy_flag', 'div' => false));
    ?>
    Malignancy, active in the past year
    <br/>
    <?php 
      echo $form->input('Review.cc_chemo_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_chemo_flag', 'div' => false));
    ?>
    Chemotherapy in the past 90 days
    <br/>
    <?php 
      echo $form->input('Review.cc_heartfailure_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_heartfailure_flag', 'div' => false));
    ?>
    Heart failure prior to event
    <br/>
    <?php 
      echo $form->input('Review.cc_ns_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_ns_flag', 'div' => false));
    ?>
    Nephrotic syndrome
    <br/>
    <?php 
      echo $form->input('Review.cc_dialysis_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_dialysis_flag', 'div' => false));
    ?>
    Dialysis
    <br/>
    <?php 
      echo $form->input('Review.cc_hosp_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_hosp_flag', 'div' => false));
    ?>
    Hospitalization in the past 90 days
    <br/>
    <?php 
      echo $form->input('Review.cc_mt_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_mt_flag', 'div' => false));
    ?>
    Major trauma including fracture in past 90 days
    <br/>
    <?php 
      echo $form->input('Review.cc_immob_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_immob_flag', 'div' => false));
    ?>
    Immobilization/bed rest in the past 90 days
    <br/>
    <?php 
      echo $form->input('Review.cc_longride_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_longride_flag', 'div' => false));
    ?>
    Long plane ride/prolonged sitting in the past 30 days
    <br/>
    <?php 
      echo $form->input('Review.cc_surgery_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_surgery_flag', 'div' => false));
    ?>
    Surgery in past 90 days
    <br/>
    <?php 
      echo $form->input('Review.cc_infection_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_infection_flag', 'div' => false));
    ?>
    Infection in the past 90 days
    <div id='cc_infection'>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.cc_infection_pneumonia_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_infection_pneumonia_flag', 'div' => false));
        ?>
      Pneumonia
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.cc_infection_sepsis_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'cc_infection_sepsis_flag', 'div' => false));
      ?>
      Sepsis/bacteremia
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.cc_infection_uti_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'cc_infection_uti_flag', 'div' => false));
      ?>
      UTI/pyelonephritis
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.cc_infection_endocarditis_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'cc_infection_endocarditis_flag', 'div' => false));
      ?>
      Endocarditis
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.cc_infection_osteomyelitis_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'cc_infection_osteomyelitis_flag', 'div' => false));
      ?>
      Osteomyelitis
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.cc_infection_meningitis_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'cc_infection_meningitis_flag', 'div' => false));
      ?>
      Meningitis
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.cc_infection_cellulitis_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'cc_infection_cellulitis_flag', 'div' => false));
      ?>
      Cellulitis/skin abscess
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.cc_infection_covid_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'cc_infection_covid_flag', 'div' => false));
      ?>
      COVID
      <br/>
      &nbsp;&nbsp;
      <?php 
        echo $form->input('Review.cc_infection_other_flag',
                     array('type' => 'checkbox', 'label' => '', 
                           'id' => 'cc_infection_other_flag', 'div' => false));
      ?>
      Other
      <div id='cc_infection_other'>
        <?php
          echo $form->input("Review.cc_infection_other", array('label' => 'Specify:',
                                                'id' => 'ccInfectionOtherInput'));
        ?>
      </div>
    </div>
    <br/>
    <?php 
      echo $form->input('Review.cc_transfusion_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_transfusion_flag', 'div' => false));
    ?>
    Transfusion in past 30 days
    <br/>
    <?php 
      echo $form->input('Review.cc_inherited_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_inherited_flag', 'div' => false));
    ?>
    Inherited or acquired thrombophilia (other than malignancy)
    <br/>
    <?php 
      echo $form->input('Review.cc_ivdrug_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_ivdrug_flag', 'div' => false));
    ?>
    IV drug use
    <div id='iv_drug'>
      Current or Past?
      <br/>
      <?php 
        echo $form->select('Review.cc_ivdrug_use', $ivDrugUses, null, 
                           array('id' => 'ivDrugSelect'));
      ?>
    </div>
    <br/>
    <?php 
      echo $form->input('Review.cc_copd_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_copd_flag', 'div' => false));
    ?>
    COPD
    <br/>
    <?php 
      echo $form->input('Review.cc_ph_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_ph_flag', 'div' => false));
    ?>
    Pulmonary hypertension
    <br/>
    <?php 
      echo $form->input('Review.cc_steroid_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_steroid_flag', 'div' => false));
    ?>
    Estrogen and/or progestin or anabolic steroid use in last 30 days
    <br/>
    <?php 
      echo $form->input('Review.cc_pregnancy_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_pregnancy_flag', 'div' => false));
    ?>
    Current pregnancy or within 3 months post-partum
    <br/>
    <?php 
      echo $form->input('Review.cc_other_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_other_flag', 'div' => false));
    ?>
    Other conditions predisposing to VTE
    <div id='cc_other'>
      <?php
        echo $form->input("Review.cc_other", array('label' => 'Specify:',
                                              'id' => 'ccOtherInput'));
      ?>
    </div>
    <br/>
    <?php 
      echo $form->input('Review.cc_unknown_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_unknown_flag', 'div' => false));
    ?>
    Unknown
    <br/>
    <?php 
      echo $form->input('Review.cc_none_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'cc_none_flag', 'div' => false));
    ?>
    None
  </td>
</tr>

<tr class='mc'>
  <td colspan="2"><hr/></td>
</tr>

<tr class='mc'>
  <th>Please provide the following additional information</th>
  <td>
    Smoking status?
    <br/>
    <?php 
      echo $form->select('Review.smoking_use', $smokingUses, null, 
                         array('id' => 'smokingUseSelect'));
    ?>
    <br/>
    <br/>
    History of prior VTE? (mark all that apply)
    <br/>
    <?php 
      echo $form->input('Review.vtehistory_pe_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'vtehistory_pe_flag', 'div' => false));
    ?>
    PE
    <br/>
    <?php 
      echo $form->input('Review.vtehistory_dvt_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'vtehistory_dvt_flag', 'div' => false));
    ?>
    DVT
    <br/>
    <?php 
      echo $form->input('Review.vtehistory_unknowntype_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'vtehistory_unknowntype_flag', 'div' => false));
    ?>
    Unknown type
    <br/>
    <?php 
      echo $form->input('Review.vtehistory_none_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'vtehistory_none_flag', 'div' => false));
    ?>
    None
    <br/>
    <?php 
      echo $form->input('Review.vtehistory_unknown_flag',
                      array('type' => 'checkbox', 'label' => '', 
                            'id' => 'vtehistory_unknown_flag', 'div' => false));
    ?>
    Unknown
    <br/>
    <br/>
    Family history of VTE?
    <br/>
    <?php 
      echo $form->select('Review.family_history', $multipleChoices, null, 
                         array('id' => 'familyHistorySelect'));
    ?>
  </td>
</tr>

<tr class='management'>
  <td colspan="2"><hr/></td>
</tr>

<tr class='management'>
  <th>Please provide the following information regarding management.</th>
  <td>
    Is information available on management?
    <br/>
    <?php
      echo $form->radio('Review.management_info', array(1 => 'Yes&nbsp;&nbsp;', 0 => 'No'),
                        array('legend' => false, 'id' => 'managementInfoRadio'));
    ?>

    <div id='management_more'>
      <br/>
      Already on anticoagulation therapy or have a VC filter at time of VTE diagnosis?
      <br/>
      <?php
        echo $form->select('Review.management_at', $managementAts, null, 
                         array('id' => 'managementAtSelect'));
      ?>
      <br/>
      VTE occurred after admission to the hospital?
      <br/>
      <?php
        echo $form->select('Review.management_hosp', $multipleChoices, null, 
                         array('id' => 'managementHospSelect'));
      ?>
      <br/>
      Vena cava filter placed?
      <br/>
      <?php
        echo $form->select('Review.management_vcf', $multipleChoices, null, 
                         array('id' => 'managementVcfSelect'));
      ?>
      <br/>
      Thrombolytic therapy?
      <br/>
      <?php
        echo $form->select('Review.management_tt', $multipleChoices, null, 
                         array('id' => 'managementTtSelect'));
      ?>
      <br/>
      Thrombectomy?
      <br/>
      <?php
        echo $form->select('Review.management_thrombectomy', $multipleChoices, null, 
                         array('id' => 'managementThrombectomySelect'));
      ?>
      <br/>
      Managed as:
      <br/>
      <?php
        echo $form->select('Review.management_managedas', $managementManagedAs, null, 
                         array('id' => 'managementManagedasSelect'));
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
