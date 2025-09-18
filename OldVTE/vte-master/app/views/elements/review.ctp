<?php
if (empty($review)) {
    echo "No such review!";
} else {
    if ($review['no_vte_flag'] === '1') {
        echo "No VTE";
    } else {
        if ($review['pe_flag'] === '1') {
            echo "{$separator}<span class='subheading'>PE</span>: ";
            echo "{$review['pe_dp']}, {$review['pe_type']}";
            echo "{$separator}<span class='subheading'>Location</span>: ";

            if ($review['pe_main_flag']) {
                echo "Main pulmonary artery(ies); ";
            }
            
            if ($review['pe_lobar_flag']) {
                echo "Lobar; ";
            }
            
            if ($review['pe_segmental_flag']) {
                echo "Segmental; ";
            }
            
            if ($review['pe_subsegmental_flag']) {
                echo "Sub-segmental; ";
            }
            
            if ($review['pe_unknown_flag']) {
                echo "Unknown; ";
            }
        }

        if ($review['cat_flag'] === '1') {
            echo "{$separator}<span class='subheading'>Catheter-induced thrombosis</span>: ";
            echo "<span class='subheading'>Type</span>: {$review['cat_subtype']}";
            echo "{$separator}{$review['cat_dp']}, {$review['cat_type']}";
        }

        if ($review['dvt_flag'] === '1') {
            echo "{$separator}<span class='subheading'>DVT</span>: ";
            echo "{$review['dvt_dp']}, {$review['dvt_type']}";
	}

	if ($review['dvt_flag'] === '1' || $review['cat_flag'] === '1') {
            echo "{$separator}<span class='subheading'>Location</span>: ";

            if ($review['dvt_ue_flag']) {
                echo "UE; ";
            }
            
            if ($review['dvt_le_flag']) {
                $leMore = '';

                if ($review['dvt_le_proximal_flag']) {
                    $leMore .= 'Proximal (popliteal, femoral, iliac); ';
                }
                
                if ($review['dvt_le_distal_flag']) {
                    $leMore .= 'Distal; ';
                }

                if ($review['dvt_le_unknown_flag']) {
                    $leMore .= 'Unknown; ';
                }

                echo "LE ($leMore); ";
            }
            
            if ($review['dvt_other_flag']) {
                $otherMore = '';

                if ($review['dvt_other_neck_flag']) {
                    $otherNeckMore = '';

                    if ($review['dvt_other_neck_jugular_flag']) {
                        $otherNeckMore .= 'Jugular; ';
                    }
                    
                    if ($review['dvt_other_neck_subclavian_flag']) {
                        $otherNeckMore .= 'Subclavian; ';
                    }

                    if ($review['dvt_other_neck_brach_flag']) {
                        $otherNeckMore .= 'Brachiocephalic (innominate); ';
                    }

                    if ($review['dvt_other_neck_unknown_flag']) {
                        $otherNeckMore .= 'Unknown; ';
                    }

                    $otherMore .= "<span class='subheading'>Neck/Chest</span>: [$otherNeckMore]; ";
                }
                
                if ($review['dvt_other_vc_flag']) {
                    $otherMore .= 'Vena cava (superior or inferior); ';
                }

                if ($review['dvt_other_ap_flag']) {
                    $otherApMore = '';

                    if ($review['dvt_other_ap_renal_flag']) {
                        $otherApMore .= 'Renal; ';
                    }
                    
                    if ($review['dvt_other_ap_hepatic_flag']) {
                        $otherApMore .= 'Hepatic; ';
                    }

                    if ($review['dvt_other_ap_pelvic_flag']) {
                        $otherApMore .= 'Pelvic; ';
                    }

                    if ($review['dvt_other_ap_iliac_flag']) {
                        $otherApMore .= 'Iliac; ';
                    }

                    if ($review['dvt_other_ap_unknown_flag']) {
                        $otherApMore .= 'Unknown; ';
                    }

                    $otherMore .= "<span class='subheading'>Abdomen/Pelvis</span>: [$otherApMore]; ";
                }
                
                if ($review['dvt_other_ps_flag']) {
                    $otherPsMore = '';

                    if ($review['dvt_other_ps_hepatic_flag']) {
                        $otherPsMore .= 'Hepatic portal; ';
                    }

                    if ($review['dvt_other_ps_splenic_flag']) {
                        $otherPsMore .= 'Splenic; ';
                    }
                    
                    if ($review['dvt_other_ps_mesenteric_flag']) {
                        $otherPsMore .= 'Mesenteric; ';
                    }

                    if ($review['dvt_other_ps_unknown_flag']) {
                        $otherPsMore .= 'Unknown; ';
                    }

                    $otherMore .= "<span class='subheading'>Portal System</span>: [$otherPsMore]; ";
                }
                
                if ($review['dvt_other_ic_flag']) {
                    $otherIcMore = '';

                    if ($review['dvt_other_ic_sst_flag']) {
                        $otherIcMore .= 'Sagittal sinus thrombosis; ';
                    }

                    if ($review['dvt_other_ic_tst_flag']) {
                        $otherIcMore .= 'Transverse sinus thrombosis; ';
                    }
                    
                    if ($review['dvt_other_ic_rvt_flag']) {
                        $otherIcMore .= 'Retinal vein thrombosis; ';
                    }

                    if ($review['dvt_other_ic_unknown_flag']) {
                        $otherIcMore .= 'Unknown; ';
                    }

                    $otherMore .= "<span class='subheading'>Intercranial</span>: [$otherIcMore]; ";
                }
                
                if ($review['dvt_other_other_flag']) {
                    $otherMore .= "<span class='subheading'>Other</span>: {$review['dvt_other']}; ";
                }

                if ($review['dvt_other_unknown_flag']) {
                    $otherMore .= 'Unknown; ';
                }

                echo "Other Location ($otherMore); ";
            }

            if ($review['dvt_unknown_flag']) {
                echo "Unknown; ";
            }
            
        }

        echo "{$separator}<span class='subheading'>Possible contributing conditions</span>: ";

        if ($review['cc_malignancy_flag']) { 
            echo "Malignancy, active in the past year; ";
        }

        if ($review['cc_chemo_flag']) { 
            echo "Chemotherapy in the past 90 days; ";
        }

        if ($review['cc_heartfailure_flag']) { 
            echo "Heart failure prior to event; ";
        }

        if ($review['cc_ns_flag']) { 
            echo "Nephrotic syndrome; ";
        }

        if ($review['cc_dialysis_flag']) { 
            echo "Dialysis; ";
        }

        if ($review['cc_hosp_flag']) { 
            echo "Hospitalization in the past 90 days; ";
        }

        if ($review['cc_mt_flag']) { 
            echo "Major trauma including fracture in the past 90 days; ";
        }

        if ($review['cc_immob_flag']) { 
            echo "Immobilization/bed rest in the past 90 days; ";
        }

        if ($review['cc_longride_flag']) { 
            echo "Long plane ride/prolonged sitting in the past 30 days; ";
        }

        if ($review['cc_surgery_flag']) { 
            echo "Surgery in past 90 days; ";
        }

        if ($review['cc_infection_flag']) { 
            $infection = '';

            if ($review['cc_infection_pneumonia_flag']) {
                $infection .= "Pneumonia; ";
            }

            if ($review['cc_infection_sepsis_flag']) {
                $infection .= "Sepsis/bacteremia; ";
            }

            if ($review['cc_infection_uti_flag']) {
                $infection .= "UTI/pyelonephritis; ";
            }

            if ($review['cc_infection_endocarditis_flag']) {
                $infection .= "Endocarditis; ";
            }

            if ($review['cc_infection_osteomyelitis_flag']) {
                $infection .= "Osteomyelitis; ";
            }

            if ($review['cc_infection_meningitis_flag']) {
                $infection .= "Meningitis; ";
            }

            if ($review['cc_infection_cellulitis_flag']) {
                $infection .= "Cellulitis/skin abscess; ";
            }

            if ($review['cc_infection_covid_flag']) {
                $infection .= "COVID; ";
            }

            if ($review['cc_infection_other_flag']) {
                $infection .= "<span class='subheading'>Other</span>: {$review['cc_infection_other']}; ";
            }

            echo "Infection in the past 90 days: ($infection); ";
        }

        if ($review['cc_transfusion_flag']) { 
            echo "Transfusion in past 30 days; ";
        }

        if ($review['cc_inherited_flag']) { 
            echo "Inherited or acquired thrombophilia (other than malignancy); ";
        }

        if ($review['cc_ivdrug_flag']) { 
            echo "IV drug use, {$review['cc_ivdrug_use']}; ";
        }

        if ($review['cc_copd_flag']) { 
            echo "COPD ";
        }

        if ($review['cc_ph_flag']) { 
            echo "Pulmonary hypertenstion ";
        }

        if ($review['cc_steroid_flag']) { 
            echo "Estrogen and/or progestin or anabolic steroid use in last 30 days; ";
        }

        if ($review['cc_pregnancy_flag']) { 
            echo "Current pregnancy or within 3 months post-partum; ";
        }

        if ($review['cc_other_flag']) { 
            echo "<span class='subheading'>Other conditions predisposing to VTE</span>: {$review['cc_other']}; ";
        }

        if ($review['cc_none_flag']) { 
            echo "None ";
        }

        if ($review['cc_unknown_flag']) { 
            echo "Unknown ";
        }

        echo "{$separator}<span class='subheading'>Smoking status</span>: {$review['smoking_use']}";
        echo "{$separator}<span class='subheading'>History of prior VTE</span>: ";

        if ($review['vtehistory_pe_flag']) {
            echo "PE; ";
        }

        if ($review['vtehistory_dvt_flag']) {
            echo "DVT; ";
        }

        if ($review['vtehistory_unknowntype_flag']) {
            echo "Unknown type; ";
        }

        if ($review['vtehistory_none_flag']) {
            echo "None; ";
        }
    
        if ($review['vtehistory_unknown_flag']) {
            echo "Unknown; ";
        }
    
        echo "{$separator}<span class='subheading'>Family history of VTE</span>: {$review['family_history']}";
    
        echo "{$separator}<span class='subheading'>Management info</span>: ";
    
        if (!$review['management_info']) {
            echo "none";
        } else {
            echo "{$separator}&nbsp;Already on anticoagulation therapy or have a VC filter at time of VTE diagnosis? {$review['management_at']}";
            echo "{$separator}&nbsp;VTE occurred after admission to the hospital? {$review['management_hosp']}";
            echo "{$separator}&nbsp;Vena cava filter placed? {$review['management_vcf']}";
            echo "{$separator}&nbsp;Thrombolytic therapy? {$review['management_tt']}";
            echo "{$separator}&nbsp;Thrombectomy? {$review['management_thrombectomy']}";
            echo "{$separator}&nbsp;Managed as: {$review['management_managedas']}";
        }
    }
}
?>
