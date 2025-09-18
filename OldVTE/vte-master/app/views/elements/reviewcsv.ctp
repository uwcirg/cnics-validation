<?php
if (empty($review)) {
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
    $csv->addField('');
} else {
    $peFlag = $review['pe_flag'];
    $dvtFlag = $review['dvt_flag'];
    $catFlag = $review['cat_flag'];
    $noVteFlag = $review['no_vte_flag'];
    $csv->addField($peFlag ? 'Yes' : 'No');
    $csv->addField($dvtFlag ? 'Yes' : 'No');
    $csv->addField($catFlag ? 'Yes' : 'No');
    $csv->addField($noVteFlag ? 'Yes' : 'No');

    if (!$peFlag) {
        $csv->addField('');  // PE-specific fields
        $csv->addField('');
        $csv->addField('');
    } else {
        $csv->addField($review['pe_dp']);
        $csv->addField($review['pe_type']);
        $peLocation = '';

        if ($review['pe_main_flag']) {
            $peLocation .= 'Main pulmonary artery(ies); ';
        }

        if ($review['pe_lobar_flag']) {
            $peLocation .= 'Lobar; ';
        }

        if ($review['pe_segmental_flag']) {
            $peLocation .= 'Segmental; ';
        }

        if ($review['pe_subsegmental_flag']) {
            $peLocation .= 'Subsegmental; ';
        }

        if ($review['pe_unknown_flag']) {
            $peLocation .= 'Unknown; ';
        }

        $csv->addField($peLocation);
    }

    if (!$dvtFlag) {
        $csv->addField('');  // DVT-specific fields
        $csv->addField('');
    } else {
        $csv->addField($review['dvt_dp']);
        $csv->addField($review['dvt_type']);
    }

    if (!$catFlag) {
        $csv->addField('');  // CAT-specific fields
        $csv->addField('');
        $csv->addField('');
    } else {
        $csv->addField($review['cat_dp']);
        $csv->addField($review['cat_type']);
        $csv->addField($review['cat_subtype']);
    }

    if (!$dvtFlag && !$catFlag) {
        $csv->addField('');  // CAT/DVT location fields
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
    } else {
        $dvtLocation = '';
        $dvtLeLocation = '';
        $dvtOtherLocation = '';
        $dvtOtherNeckLocation = '';
        $dvtOtherApLocation = '';
        $dvtOtherPsLocation = '';
        $dvtOtherIcLocation = '';

        if ($review['dvt_ue_flag']) {
            $dvtLocation .= 'UE; ';
        }

        if ($review['dvt_le_flag']) {
            $dvtLocation .= 'LE; ';

            if ($review['dvt_le_proximal_flag']) {
                $dvtLeLocation .= 'Proximal; ';
            }

            if ($review['dvt_le_distal_flag']) {
                $dvtLeLocation .= 'Distal; ';
            }

            if ($review['dvt_le_unknown_flag']) {
                $dvtLeLocation .= 'Unknown; ';
            }
        }

        if ($review['dvt_other_flag']) {
            $dvtLocation .= 'Other; ';

            if ($review['dvt_other_neck_flag']) {
                $dvtOtherLocation .= 'Neck/Chest; ';

                if ($review['dvt_other_neck_jugular_flag']) {
                    $dvtOtherNeckLocation .= 'Jugular; ';
                }

                if ($review['dvt_other_neck_subclavian_flag']) {
                    $dvtOtherNeckLocation .= 'Subclavian; ';
                }

                if ($review['dvt_other_neck_brach_flag']) {
                    $dvtOtherNeckLocation .= 'Brachiocephalic; ';
                }

                if ($review['dvt_other_neck_unknown_flag']) {
                    $dvtOtherNeckLocation .= 'Unknown; ';
                }
            }

            if ($review['dvt_other_vc_flag']) {
                $dvtOtherLocation .= 'Vena cava; ';
            }

            if ($review['dvt_other_ap_flag']) {
                $dvtOtherLocation .= 'Abdomen/pelvis; ';

                if ($review['dvt_other_ap_renal_flag']) {
                    $dvtOtherApLocation .= 'Renal; ';
                }

                if ($review['dvt_other_ap_hepatic_flag']) {
                    $dvtOtherApLocation .= 'Hepatic; ';
                }

                if ($review['dvt_other_ap_pelvic_flag']) {
                    $dvtOtherApLocation .= 'Pelvic; ';
                }

                if ($review['dvt_other_ap_iliac_flag']) {
                    $dvtOtherApLocation .= 'Iliac; ';
                }

                if ($review['dvt_other_ap_unknown_flag']) {
                    $dvtOtherApLocation .= 'Unknown; ';
                }
            }

            if ($review['dvt_other_ps_flag']) {
                $dvtOtherLocation .= 'Portal System; ';

                if ($review['dvt_other_ps_hepatic_flag']) {
                    $dvtOtherPsLocation .= 'Hepatic portal; ';
                }

                if ($review['dvt_other_ps_splenic_flag']) {
                    $dvtOtherPsLocation .= 'Splenic; ';
                }

                if ($review['dvt_other_ps_mesenteric_flag']) {
                    $dvtOtherPsLocation .= 'Mesenteric; ';
                }

                if ($review['dvt_other_ps_unknown_flag']) {
                    $dvtOtherPsLocation .= 'Unknown; ';
                }
            }

            if ($review['dvt_other_ic_flag']) {
                $dvtOtherLocation .= 'Intercranial; ';

                if ($review['dvt_other_ic_sst_flag']) {
                    $dvtOtherIcLocation .= 'Sagittal sinus thrombosis; ';
                }

                if ($review['dvt_other_ic_tst_flag']) {
                    $dvtOtherIcLocation .= 'Transversel sinus thrombosis; ';
                }

                if ($review['dvt_other_ic_rvt_flag']) {
                    $dvtOtherIcLocation .= 'Retinal vein thrombosis; ';
                }

                if ($review['dvt_other_ic_unknown_flag']) {
                    $dvtOtherIcLocation .= 'Unknown; ';
                }
            }

            if ($review['dvt_other_other_flag']) {
                $dvtOtherLocation .= 'Other; ';
            }

            if ($review['dvt_other_unknown_flag']) {
                $dvtOtherLocation .= 'Unknown; ';
            }
        }

        if ($review['dvt_unknown_flag']) {
            $dvtLocation .= 'Unknown; ';
        }


        $csv->addField($dvtLocation);
        $csv->addField($dvtLeLocation);
        $csv->addField($dvtOtherLocation);
        $csv->addField($dvtOtherNeckLocation);
        $csv->addField($dvtOtherApLocation);
        $csv->addField($dvtOtherPsLocation);
        $csv->addField($dvtOtherIcLocation);
        $csv->addField($review['dvt_other']);
    }

    if (!$peFlag && !$dvtFlag && !$catFlag) {
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
        $csv->addField('');
    } else {  // some type of VTE
        $cc = '';
        $ccInfection = '';

        if ($review['cc_malignancy_flag']) {
            $cc .= 'Malignancy; ';
        }

        if ($review['cc_chemo_flag']) {
            $cc .= 'Chemotherapy; ';
        }

        if ($review['cc_heartfailure_flag']) {
            $cc .= 'Heart Failure prior; ';
        }

        if ($review['cc_ns_flag']) {
            $cc .= 'Nephrotic syndrome; ';
        }

        if ($review['cc_dialysis_flag']) {
            $cc .= 'Dialysis; ';
        }

        if ($review['cc_hosp_flag']) {
            $cc .= 'Hospitalization; ';
        }

        if ($review['cc_mt_flag']) {
            $cc .= 'Major trauma; ';
        }

        if ($review['cc_immob_flag']) {
            $cc .= 'Immobilization/bed rest; ';
        }

        if ($review['cc_longride_flag']) {
            $cc .= 'Long plane ride/sitting; ';
        }

        if ($review['cc_surgery_flag']) {
            $cc .= 'Surgery; ';
        }

        if ($review['cc_infection_flag']) {
            $cc .= 'Infection; ';

            if ($review['cc_infection_pneumonia_flag']) {
                $ccInfection .= 'Pneumonia; ';
            }

            if ($review['cc_infection_sepsis_flag']) {
                $ccInfection .= 'Sepsis/bacteremia; ';
            }

            if ($review['cc_infection_uti_flag']) {
                $ccInfection .= 'UTI/pyelonephritis; ';
            }

            if ($review['cc_infection_endocarditis_flag']) {
                $ccInfection .= 'Endocarditis; ';
            }

            if ($review['cc_infection_osteomyelitis_flag']) {
                $ccInfection .= 'Osteomyelits; ';
            }

            if ($review['cc_infection_meningitis_flag']) {
                $ccInfection .= 'Meningitis; ';
            }

            if ($review['cc_infection_cellulitis_flag']) {
                $ccInfection .= 'Cellulitis/skin abscess; ';
            }

            if ($review['cc_infection_covid_flag']) {
                $ccInfection .= 'COVID; ';
            }

            if ($review['cc_infection_other_flag']) {
                $ccInfection .= 'Other; ';
            }
        }

        if ($review['cc_transfusion_flag']) {
            $cc .= 'Transfusion; ';
        }

        if ($review['cc_inherited_flag']) {
            $cc .= 'Inherited/acquired thrombophilia; ';
        }

        if ($review['cc_ivdrug_flag']) {
            $cc .= 'IV drug use; ';
        }

        if ($review['cc_copd_flag']) {
            $cc .= 'COPD; ';
        }

        if ($review['cc_ph_flag']) {
            $cc .= 'Pulmonary Hypertension; ';
        }

        if ($review['cc_steroid_flag']) {
            $cc .= 'Estrogen/progestin/anabolic steroid use; ';
        }

        if ($review['cc_pregnancy_flag']) {
            $cc .= 'Pregnancy; ';
        }

        if ($review['cc_other_flag']) {
            $cc .= 'Other; ';
        }

        if ($review['cc_unknown_flag']) {
            $cc .= 'Unknown; ';
        }

        if ($review['cc_none_flag']) {
            $cc .= 'None; ';
        }

        $csv->addField($cc);
        $csv->addField($ccInfection);
        $csv->addField($review['cc_infection_other']);
        $csv->addField($review['cc_ivdrug_use']);
        $csv->addField($review['cc_other']);

        $csv->addField($review['smoking_use']);
       
        $ph = '';

        if ($review['vtehistory_pe_flag']) {
            $ph .= 'PE; ';
        }

        if ($review['vtehistory_dvt_flag']) {
            $ph .= 'DVT; ';
        }

        if ($review['vtehistory_unknowntype_flag']) {
            $ph .= 'Unknown type; ';
        }

        if ($review['vtehistory_none_flag']) {
            $ph .= 'None; ';
        }

        if ($review['vtehistory_unknown_flag']) {
            $ph .= 'Unknown; ';
        }

        $csv->addField($ph);
        $csv->addField($review['family_history']);

        $mi = $review['management_info'];
        $csv->addField($mi ? 'Yes' : 'No');

        if (!$mi) {
            $csv->addField('');
            $csv->addField('');
            $csv->addField('');
            $csv->addField('');
            $csv->addField('');
            $csv->addField('');
        } else {
            $csv->addField($review['management_at']);
            $csv->addField($review['management_hosp']);
            $csv->addField($review['management_vcf']);
            $csv->addField($review['management_tt']);
            $csv->addField($review['management_thrombectomy']);
            $csv->addField($review['management_managedas']);
        }
    }
}
?>
