//Para funcionar no esp8266
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h> 

const char* ssid = "NOS_Internet_A31B";    
const char* password = "81513167"; 

void setup(void) {
  //Serial.begin(9600);
  pinMode(0, OUTPUT); //Para dar sinal ao arduino que existe acesso à internet
  digitalWrite(0, LOW); //Quando o módulo liga não está

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    //Serial.println("Connecting to WiFi...");
  }
  //Serial.println("Connected to WiFi");
  digitalWrite(0, HIGH); // Diz ao arduino que já há acesso à internet
  delay(1000); //espera 10 segundos (ajustar para valor menor no futuro)
  digitalWrite(0, LOW); //Volta a por o pino em LOW
}

void loop(void) {

}
