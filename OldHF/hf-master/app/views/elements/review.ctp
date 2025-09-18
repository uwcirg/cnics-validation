<?php
if (empty($review)) {
    echo "No such review!";
} else {
    $signs = $review['signs_flag'] ? 'Yes' : 'No';
    $diagnose = $review['diagnose_flag'] ? 'Yes' : 'No';
    $prescribed = $review['prescribed_flag'] ? 'Yes' : 'No';
    echo "{$separator}<span class='subheading'>Signs and symptoms?</span>: $signs";
    echo "{$separator}<span class='subheading'>Physician diagnose HF?</span>: $diagnose";
    echo "{$separator}<span class='subheading'>Prescribed Medication?</span>: $prescribed";

    if ($signs == 'No' || $diagnose == 'No' || $prescribed == 'No') {
    	echo "{$separator}<span class='subheading'>Type of HF</span>: {$review['hf_type']}";
    } else {
        echo "{$separator}<span class='subheading'>Onset date</span>: {$review['onset_date']}";
    	echo "{$separator}<span class='subheading'>Possible volume overload/congestion?</span>: {$review['congestion']}";
        $echoPerformed = $review['echo_flag'] ? 'Yes' : 'No';
        echo "{$separator}<span class='subheading'>Echocardiogram performed?</span>: $echoPerformed";

        if ($review['echo_flag'] === '1') {
    	    echo "{$separator}<span class='subheading'>LVEF</span>: {$review['lvef']}";
            $lowLvef = $review['low_lvef_flag'] ? 'Yes' : 'No';
            $wma = $review['wma_flag'] ? 'Yes' : 'No';
            $dd = $review['dd_flag'] ? 'Yes' : 'No';
            $msvd = $review['msvd_flag'] ? 'Yes' : 'No';
            echo "{$separator}<span class='subheading'>LVEF < 50?</span>: $lowLvef";
            echo "{$separator}<span class='subheading'>Wall motion abnormalities?</span>: $wma";
            echo "{$separator}<span class='subheading'>Diastolic dysfunction?</span>: $dd";
            echo "{$separator}<span class='subheading'>Moderate-severe valve disease?</span>: $msvd";
	}

    	echo "{$separator}<span class='subheading'>Type of HF</span>: {$review['hf_type']}";

    	echo "{$separator}<span class='subheading'>Classification</span>: {$review['classification']}";
	echo "{$separator}<span class='subheading'>Etiology</span>: ";

        if ($review['ischemic_flag']) {
            echo "Ischemic; ";
        }
            
        if ($review['nonischemic_flag']) {
            echo "Non-ischemic; ";
        }
            
        if ($review['unknown_flag']) {
            echo "Unknown; ";
        }
            
        if ($review['nonischemic_flag']) {
    	    $ni = '';

            if ($review['ni_valvular_flag']) {
                $ni .= "valvular; ";
            }

            if ($review['ni_infiltrative_flag']) {
                $ni .= "infiltrative; ";
            }

            if ($review['ni_im_flag']) {
                $ni .= "inflammatory myocarditis; ";
            }

            if ($review['ni_obstructive_flag']) {
                $ni .= "obstructive; ";
            }

            if ($review['ni_recreational_flag']) {
                $ni .= "toxic-recreational drugs; ";
            }

            if ($review['ni_prescription_flag']) {
                $ni .= "toxic-prescription drugs; ";
            }

            if ($review['ni_hypertensive_flag']) {
                $ni .= "hypertensive; ";
            }

            if ($review['ni_renal_flag']) {
                $ni .= "uremic/renal; ";
            }

            if ($review['ni_cardiomyopathy_flag']) {
                $ni .= "HIV / AIDS cardiomyopathy; ";
            }

            if ($review['ni_covid_flag']) {
                $ni .= "COVID; ";
            }

            if ($review['ni_sepsis_flag']) {
                $ni .= "infectious/sepsis; ";
            }

            if ($review['ni_metabolic_flag']) {
                $ni .= "metabolic; ";
            }

            if ($review['ni_pd_flag']) {
                $ni .= "pericardial disease; ";
            }

            if ($review['ni_hypertrophic_flag']) {
                $ni .= "hypertrophic cardiomyopathy; ";
            }

            if ($review['ni_other_flag']) {
                $ni .= "other: {$review['ni_other']}; ";
            }

    	    echo "{$separator}<span class='subheading'>Non-ischemic subcategories</span>: ($ni); ";
	}

    	echo "{$separator}<span class='subheading'>Presentation</span>: {$review['presentation']}";

        $moreInfo = $review['more_info_flag'] ? 'Yes' : 'No';
        echo "{$separator}<span class='subheading'>More info needed?</span>: $moreInfo";

	if ($moreInfo == 'Yes') {
    	    echo "{$separator}<span class='subheading'>Needed Info:</span>: {$review['need_more']}";
        }
    }
}
?>
