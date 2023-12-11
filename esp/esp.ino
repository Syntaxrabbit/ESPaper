/**
  ******************************************************************************
  * @file    esp.ino
  * @author  SyntaxRabbit
  * @version V1.0.0
  * @date    11-December-2023
  * @brief   ESP32 WiFi server with MQTT
  *
  ******************************************************************************
  */


#include <WiFi.h>
#include "soc/rtc_cntl_reg.h"
#include "srvr.h"  // Server functions

const char* ssid = "IOT";           // Change this to your WiFi SSID
const char* password = "<password>";  // Change this to your WiFi password

const int sleepTime = 120;

const float TICKS_PER_SECOND = 80000000;  // 80 MHz processor
const int UPTIME_SEC = 15;

void setup() {
  WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0);  //disable brownout detector

  Serial.begin(115200);
  while (!Serial) { delay(100); }

  Serial.println();
  Serial.println("******************************************************");
  Serial.print("Connecting to ");
  Serial.println(ssid);

  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println("");
  Serial.println("WiFi connected");
  Serial.println("IP address: ");
  Serial.println(WiFi.localIP());

  Srvr__setup();

  EPD_initSPI();
}

void loop() {
  Srvr__loop();

  bool isTimeToSleep = false;

  static unsigned long startCycle = ESP.getCycleCount();
  unsigned long currentCycle = ESP.getCycleCount();
  unsigned long difference;

  if (currentCycle < startCycle) {
    difference = (4294967295 - startCycle + currentCycle);
  } else {
    difference = (currentCycle - startCycle);
  }

  int decile = fmod(difference / (TICKS_PER_SECOND / 100.0), 100.0);

  if (difference > UPTIME_SEC * TICKS_PER_SECOND) {
    isTimeToSleep = true;
  }

  bool refreshIdle = !Srvr__updateRunning();
  if (isTimeToSleep && refreshIdle) {
    Serial.println("Deep sleep started");
    ESP.deepSleep(sleepTime * 1000000);
    delay(100);
  }
}
