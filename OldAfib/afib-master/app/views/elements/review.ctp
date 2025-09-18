<?php
if (empty($review)) {
    echo "No such review!";
} else {
    if ($review['no_af_flag'] === '1') {
        echo "{$separator}<span class='subheading'>No A fib and no A flutter</span>";
    } else {
	$onlyHistory = true;

        if ($review['afib_flag'] === '1') {
            echo "{$separator}<span class='subheading'>A fib</span>: ";

            if ($review['afib_encounter_flag']) {
                echo "During this encounter; ";
	        $onlyHistory = false;
            }
            
            if ($review['afib_history_flag']) {
                echo "History of A fib; ";
            }
        }

        if ($review['aflutter_flag'] === '1') {
            echo "{$separator}<span class='subheading'>A flutter</span>: ";

            if ($review['aflutter_encounter_flag']) {
                echo "During this encounter; ";
	        $onlyHistory = false;
            }
            
            if ($review['aflutter_history_flag']) {
                echo "History of A flutter; ";
            }
        }

        if ($review['af_foundonly_flag'] === '1') {
            echo "{$separator}<span class='subheading'>AF found only on pacer, AICD or Holter, during this encounter</span>.";
	    $onlyHistory = false;
        }

	if ($onlyHistory) { // nothing else to do
	    return;
	}

    	echo "{$separator}<span class='subheading'>AF Timing</span>: {$review['af_timing']}";
    
    	echo "{$separator}<span class='subheading'>Possible Associated Conditions</span>: ";
    
        if ($review['assoc_coronary_flag']) { 
            $cad = '';

            if ($review['assoc_coronary_angina_flag']) {
                $cad .= "angina or ACS during current encounter; ";
            }

            if ($review['assoc_coronary_cabg_flag']) {
                $cad .= "CABG within 30 days prior; ";
            }

            echo "coronary artery disease: ($cad); ";
        }

        if ($review['assoc_mi_flag']) { 
            $mi = '';

            if ($review['assoc_mi_30_flag']) {
                $mi .= "within 30 days prior; ";
            }

            if ($review['assoc_mi_gt30_flag']) {
                $mi .= "> 30 days prior; ";
            }

            if ($review['assoc_mi_unknown_flag']) {
                $mi .= "timing unknown; ";
            }

            echo "MI: ($mi); ";
        }

        if ($review['assoc_hf_flag']) { 
            $hf = '';

            if ($review['assoc_hf_exacerbation_flag']) {
                $hf .= "current HF exacerbation; ";
            }

            echo "heart failure: ($hf); ";
        }

        if ($review['assoc_vhd_flag']) { 
            $vhd = '';

            if ($review['assoc_vhd_surgery_flag']) {
                $vhd .= "valve surgery within 30 days prior; ";
            }

            if ($review['assoc_vhd_disease_flag']) {
                $vhd .= "valve disease related to endocarditis; ";
            }

            echo "valvular heart disease: ($vhd); ";
        }

        if ($review['assoc_copd_flag']) { 
            $copd = '';

            if ($review['assoc_copd_exacerbation_flag']) {
                $copd .= "current COPD exacerbation; ";
            }

            echo "COPD: ($copd); ";
        }

        if ($review['assoc_stroke_flag']) { 
            $stroke = '';

            if ($review['assoc_stroke_current_flag']) {
                $stroke .= "during current encounter; ";
            }

            echo "ischemic stroke/transient ischemic attack: ($stroke); ";
        }

        if ($review['assoc_infection_flag']) { 
            $infection = '';

            if ($review['assoc_infection_sepsis_flag']) {
                $infection .= "sepsis; ";
            }

            if ($review['assoc_infection_bacteremia_flag']) {
                $infection .= "bacteremia/fungemia; ";
            }

            if ($review['assoc_infection_pneumonia_flag']) {
                $infection .= "pneumonia; ";
            }

            if ($review['assoc_infection_other_flag']) {
                $infection .= "other infection: {$review['assoc_infection_other']}; ";
            }

            echo "infection: ($infection); ";
        }

        if ($review['assoc_thoracic_flag']) { 
            $thoracic = '';

            if ($review['assoc_thoracic_malignancy_flag']) {
                $thoracic .= "malignancy; ";
            }

            if ($review['assoc_thoracic_mass_flag']) {
                $thoracic .= "mass of unknown significance; ";
            }

            if ($review['assoc_thoracic_pericarditis_flag']) {
                $thoracic .= "pericarditis; ";
            }

            if ($review['assoc_thoracic_ild_flag']) {
                $thoracic .= "interstitial lung disease; ";
            }

            if ($review['assoc_thoracic_ph_flag']) {
                $thoracic .= "pulmonary hypertension; ";
            }

            if ($review['assoc_thoracic_other_flag']) {
                $thoracic .= "other: {$review['assoc_thoracic_other']}; ";
            }

            echo "thoracic disease: ($thoracic); ";
        }

        if ($review['assoc_pvd_flag']) { 
            echo "peripheral vascular disease; ";
        }

        if ($review['assoc_surgery_flag']) { 
            echo "surgery within 30 days prior, involving general anesthesia; ";
        }

        if ($review['assoc_none_flag']) { 
            echo "none; ";
        }

        echo "{$separator}<span class='subheading'>Tobacco</span>: {$review['tobacco']}";
        echo "{$separator}<span class='subheading'>Other substance use</span>: ";

        if ($review['ha_flag']) { 
            $ha = '';

            if ($review['ha_intox_flag']) {
                $ha .= "intoxicated at presentation with AF; ";
            }

            echo "heavy alcohol: ($ha); ";
        }

        if ($review['sub_other_flag']) { 
            $so = '';

            if ($review['sub_other_marijuana_flag']) {
                $so .= "marijuana; ";
            }

            if ($review['sub_other_meth_flag']) {
                $so .= "methamphetamine/crystal";

		if ($review['sub_other_meth_intox_flag']) {
		    $so .= '--intoxicated at presentation with AF';
		}

		$so .= "; ";
            }

            if ($review['sub_other_cocaine_flag']) {
                $so .= "cocaine/crack";

		if ($review['sub_other_cocaine_intox_flag']) {
		    $so .= '--intoxicated at presentation with AF';
		}

		$so .= "; ";
            }

            if ($review['sub_other_opiate_flag']) {
                $so .= "opiate/heroin";

		if ($review['sub_other_opiate_intox_flag']) {
		    $so .= '--intoxicated at presentation with AF';
		}

		$so .= "; ";
            }

            if ($review['sub_other_unspecified_flag']) {
                $so .= "other substance/not specified; ";
            }

            echo "other substances: ($so); ";
        }

	$secondary = $review['af_secondary_flag'] ? 'Yes' : 'No';
        echo "{$separator}<span class='subheading'>Secondary to an acute condition?</span>: $secondary";

	if ($secondary == "Yes") {
	    echo "{$separator}<span class='subheading'>Secondary Condition</span>: ";

            if ($review['secondary_infection_flag']) {
                echo "infection/sepsis; ";
            }

            if ($review['secondary_alcohol_flag']) {
                echo "alcohol or drug overdose; ";
            }

            if ($review['secondary_thoracic_flag']) {
                echo "thoracic disease; ";
            }

            if ($review['secondary_postop_flag']) {
                echo "post-op; ";
            }

            if ($review['secondary_other_flag']) {
                echo "other: " . $review['secondary_other'] . '; ';
            }
	}


        if ($review['echo_flag']) { 
            echo "{$separator}<span class='subheading'>Echocardiogram</span>: ";

	    if (!empty($review['echo_ef_percent'])) {
    	        echo "LV ejection fraction " . 
	        $review['echo_ef_percent'] . '%; ';
	    }

	    if (!empty($review['echo_ef_text'])) {
    	        echo "LV ejection fraction: " . 
	        $review['echo_ef_text'] . '; ';
	    }

	    if (!empty($review['echo_lae_dimension_cm'])) {
    	        echo "LA dimension " . 
	        $review['echo_lae_dimension_cm'] . ' cm; ';
	    }

	    if (!empty($review['echo_lae_dimension_text'])) {
    	        echo "LA dimension: " . 
	        $review['echo_lae_dimension_text'] . '; ';
	    }

	    if (!empty($review['echo_lae_volume'])) {
    	        echo "LA volume index " . 
	        $review['echo_lae_volume'] . ' ml/m<sub>2</sub>; ';
	    }

	    if (!empty($review['echo_valve_disease'])) {
    	        echo "valve disease " .  $review['echo_valve_disease'] . '; ';
	    }

            if ($review['echo_lvseg_flag']) {
                echo "LV segmental wall motion abnormality; ";
            }

            if ($review['echo_lvgh_flag']) {
                echo "LV global hypokinesis; ";
            }

            if ($review['echo_lvdd_flag']) {
                echo "LV diastolic dysfunction; ";
            }

            if ($review['echo_lvh_flag']) {
                echo "LV hypertrophy; ";
            }

            if ($review['echo_lve_flag']) {
                echo "LV enlargement; ";
            }

            if ($review['echo_rv_flag']) {
                echo "RV enlargement or depressed systolic function; ";
            }

            if ($review['echo_elevatedpressure_flag']) {
                echo "elevated pulmonary artery pressure; ";
            }

            if ($review['echo_other_flag']) {
                echo "other: " . $review['echo_other'] . '; ';
            }
	}

        echo "{$separator}<span class='subheading'>AF duration subtype</span>: {$review['af_type']}";

        if ($review['antic_flag']) { 
            echo "{$separator}<span class='subheading'>Anticogulated</span>: ";

            if ($review['antic_already_flag']) { 
                $already = '';
    
                if ($review['antic_already_warfarin_flag']) {
                    $already .= "warfarin; ";
                }
    
                if ($review['antic_already_noac_flag']) {
                    $already .= "NOAC; ";
                }
    
                if ($review['antic_already_aspirin_flag']) {
                    $already .= "aspirin; ";
                }
    
                if ($review['antic_already_other_flag']) {
                    $already .= "other; ";
                }
    
                if ($review['antic_already_unknown_flag']) {
                    $already .= "unknown type; ";
                }
    
                echo "already on anticoagulant at time of this AF diagnosis: ($already); ";
            }

            if ($review['antic_prescribed_flag']) { 
                $prescribed = '';
    
                if ($review['antic_prescribed_warfarin_flag']) {
                    $prescribed .= "warfarin; ";
                }
    
                if ($review['antic_prescribed_noac_flag']) {
                    $prescribed .= "NOAC; ";
                }
    
                if ($review['antic_prescribed_aspirin_flag']) {
                    $prescribed .= "aspirin; ";
                }
    
                if ($review['antic_prescribed_other_flag']) {
                    $prescribed .= "other; ";
                }
    
                if ($review['antic_prescribed_unknown_flag']) {
                    $prescribed .= "unknown type; ";
                }
    
                echo "anticoagulant prescribed within 1 month after this AF diagnosis: ($prescribed); ";
            }
	} 
    }
}
?>
