--
-- Temporary table structure for view `patients_view`
--

DROP TABLE IF EXISTS `patients_view`;
/*!50001 DROP VIEW IF EXISTS `patients_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `patients_view` AS SELECT
 1 AS `id`,
  1 AS `site_patient_id`,
  1 AS `site`,
  1 AS `last_update`,
  1 AS `create_date` */;
SET character_set_client = @saved_cs_client;


--
-- Final view structure for view `patients_view`
--

/*!50001 DROP VIEW IF EXISTS `patients_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */

/*!50001 VIEW `patients_view` AS select `patients`.`id` AS `id`,`patients`.`site_patient_id` AS `site_patient_id`,`patients`.`site` AS `site`,`patients`.`last_update` AS `last_update`,`patients`.`create_date` AS `create_date` from `patients` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
