// ===============================================================
//                MEDIÇÃO DA TEMPERATURA – MÓDULO XBEE
// ===============================================================
//
//  Variáveis geradas pelo módulo:
//  1. Timestamp em formato Epoch
//  2. Tensão nas baterias
//  3. Temperatura do sensor 1
//  4. Temperatura do sensor 2
//
// ---------------------------------------------------------------
//  PINOS UTILIZADOS NO ARDUINO
//
//  D0  RX (serial)               – Recebe do XBee
//  D1  TX (serial)               – Envia para o XBee
//  D2  Relé para desligar Arduino (bateria fraca)
//  D3  Ativar LDO (3.3 V) do XBee (VCC do Arduino é insuficiente)
//  D4  Sleep do XBee
//  D5  Comunicação digital com sensores DS18B20
//
//  D10 SS (SPI)                  – Cartão microSD
//  D11 MOSI (SPI)                – Cartão microSD
//  D12 MISO (SPI)                – Cartão microSD
//  D13 SCK  (SPI)                – Cartão microSD
//
//  A0  VBAT                      – Verificação das baterias
//  A4  SDA (I2C)                 – RTC
//  A5  SCL (I2C)                 – RTC
//
//  RAW – entrada das baterias 18650 (em paralelo)
//  VCC – alimentação do SD, RTC e sensores de temperatura
// ---------------------------------------------------------------


#include <Wire.h>
#include <SD.h>
#include "LowPower.h"
#include <OneWire.h>
#include <DallasTemperature.h>

// ======================== CONSTANTES ===========================
#define RTC_ADDRESS   0x68
#define ONE_WIRE_BUS  5
const int chipSelect = 10;  // CS do cartão SD

// ======================== OBJETOS ==============================
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);
DeviceAddress tempDeviceAddress;  // para endereços encontrados

// ======================== VARIÁVEIS GLOBAIS ====================
unsigned long timestamp;
int minu, minu1, flag, volt;
float voltagem;
int numberOfDevices;
File dataFile;
String fileName;

// Endereços dos sensores DS18B20
DeviceAddress sensor1Address = {0x28, 0x61, 0x64, 0x0A, 0xF6, 0x03, 0x52, 0x91}; // 2861640AF6035291
DeviceAddress sensor2Address = {0x28, 0x61, 0x64, 0x0A, 0xF1, 0x5F, 0x7B, 0x6E}; // 2861640AF15F7B6E

float temp1 = NAN;
float temp2 = NAN;

// ======================== SETUP ================================
void setup() {
  Serial.begin(9600);
  while (!Serial) {;}

  Wire.begin();
  Wire.setClock(400000L);       // I2C em modo rápido (400 kHz)
  sensors.begin();

  pinMode(chipSelect, OUTPUT);
  while (!SD.begin(chipSelect)) {;}

  // Configuração dos pinos de controlo
  pinMode(2, OUTPUT); digitalWrite(2, LOW);  // desligar Arduino
  pinMode(3, OUTPUT); digitalWrite(3, LOW);  // LDO do XBee
  pinMode(4, OUTPUT); digitalWrite(4, LOW);  // Sleep do XBee (ativo LOW)

  // Teste de escrita no SD (pisca 10 vezes)
  for (int k = 1; k <= 10; k++) {
    dataFile = SD.open("TestFile.txt", FILE_WRITE);
    dataFile.println("TestFile");
    SD.remove("TestFile.txt");
    dataFile.close();
    delay(1000);
  }
}

// ======================== LOOP PRINCIPAL =======================
void loop() {

  // ------------------------------------------------------------
  // 1. Ler tempo atual (RTC) e converter para Epoch
  // ------------------------------------------------------------
  Wire.beginTransmission(RTC_ADDRESS);
  Wire.write((byte)0x00);
  Wire.endTransmission();
  Wire.requestFrom(RTC_ADDRESS, 7);

  int sec   = bcdToDec(Wire.read());
  int minuto= bcdToDec(Wire.read());
  int hou   = bcdToDec(Wire.read() & 0b111111);
  int week  = bcdToDec(Wire.read());
  int montD = bcdToDec(Wire.read());
  int mont  = bcdToDec(Wire.read());
  int years = bcdToDec(Wire.read());

  timestamp = convertToEpochTime(0, minuto, hou, montD, mont, years);

  // ------------------------------------------------------------
  // 2. Verificar estado das baterias
  // ------------------------------------------------------------
  volt = 0;
  for (int k = 1; k <= 10; k++) {
    volt += analogRead(A0);    // média de 10 leituras
    delay(1);
  }

  voltagem = (volt / 10.000 * 1.000 / 1023.000 * 3.300 * 2.000); // divisor de tensão ×2
  if (voltagem < 3.5) digitalWrite(2, HIGH);  // desliga tudo

  // ------------------------------------------------------------
  // 3. Criar nome do ficheiro e abrir para escrita
  // ------------------------------------------------------------
  fileName = getDirFilename();
  char fileNameChar[fileName.length() + 1];
  fileName.toCharArray(fileNameChar, sizeof(fileNameChar));
  dataFile = SD.open(fileNameChar, FILE_WRITE);


  //Grava as primeiras duas variáveis no cartão
  dataFile.println(timestamp);
  dataFile.println(voltagem);

  // ------------------------------------------------------------
  // 4. Aquisição de temperaturas
  // ------------------------------------------------------------
  numberOfDevices = sensors.getDeviceCount();
  sensors.requestTemperatures();

  for (int i = 0; i < numberOfDevices; i++) {
    if (sensors.getAddress(tempDeviceAddress, i)) {

      // Sensor 1
      if (compareAddress(tempDeviceAddress, sensor1Address)) {
        temp1 = sensors.getTempC(tempDeviceAddress);
        dataFile.println("2861640AF6035291");
        dataFile.println(temp1);
      }

      // Sensor 2
      else if (compareAddress(tempDeviceAddress, sensor2Address)) {
        temp2 = sensors.getTempC(tempDeviceAddress);
        dataFile.println("2861640AF15F7B6E");
        dataFile.println(temp2);
      }
    }
  }

  // ------------------------------------------------------------
  // 5. Envio dos dados via XBee
  // ------------------------------------------------------------
  digitalWrite(3, HIGH);   // liga o XBee
  delay(1000);             // espera arranque

  Serial.print('a'); Serial.print(1); Serial.print('b'); Serial.print(timestamp);         Serial.print('c');
  Serial.print('a'); Serial.print(2); Serial.print('b'); Serial.print(int(voltagem*100)); Serial.print('c');
  Serial.print('a'); Serial.print(3); Serial.print('b'); Serial.print(int(temp1*100));    Serial.print('c');
  Serial.print('a'); Serial.print(4); Serial.print('b'); Serial.print(int(temp2*100));    Serial.print('c');

  delay(1000);
  digitalWrite(3, LOW);    // desliga o XBee

  dataFile.close();

  // ------------------------------------------------------------
  // 6. Dormir durante 10 minutos
  // ------------------------------------------------------------
  do {
    LowPower.powerDown(SLEEP_8S, ADC_OFF, BOD_OFF);

    minu1 = minu;  // guarda minuto anterior
    Wire.beginTransmission(RTC_ADDRESS);
    Wire.write((byte)0x00);
    Wire.endTransmission();
    Wire.requestFrom(RTC_ADDRESS, 2);

    int secu = bcdToDec(Wire.read());
    minu = bcdToDec(Wire.read());

    if ((minu == 0 && minu1 == 59) || (minu == 10 && minu1 == 9) ||
        (minu == 20 && minu1 == 19) || (minu == 30 && minu1 == 29) ||
        (minu == 40 && minu1 == 39) || (minu == 50 && minu1 == 49))
      flag = 1;
    //if (minu1 != minu) //ativar para o caso de se querer ciclo de 1 minuto
    //  flag = 1;
    else
      flag = 0;

  } while (flag == 0);
}

// ===============================================================
//                    FUNÇÕES AUXILIARES
// ===============================================================

// Converte BCD em decimal
byte bcdToDec(byte val) {
  return ( (val / 16 * 10) + (val % 16) );
}

// ---------------------------------------------------------------
// Cria diretório e nome de ficheiro com base na hora RTC
// ---------------------------------------------------------------
String getDirFilename() {
  Wire.beginTransmission(RTC_ADDRESS);
  Wire.write((byte)0x00);
  Wire.endTransmission();
  Wire.requestFrom(RTC_ADDRESS, 7);

  int sec   = bcdToDec(Wire.read());
  int minuto= bcdToDec(Wire.read());
  int hou   = bcdToDec(Wire.read() & 0b111111);
  int week  = bcdToDec(Wire.read());
  int montD = bcdToDec(Wire.read());
  int mont  = bcdToDec(Wire.read());
  int years = bcdToDec(Wire.read());

  String direc = String(years);
  direc += (mont > 9)  ? String(mont)  : "0" + String(mont);
  direc += (montD > 9) ? String(montD) : "0" + String(montD);

  char pathChar[direc.length() + 1];
  direc.toCharArray(pathChar, sizeof(pathChar));
  if (!SD.exists(pathChar)) SD.mkdir(pathChar);

  String filen = "";
  filen += (hou > 9)     ? String(hou)     : "0" + String(hou);
  filen += (minuto > 9)  ? String(minuto)  : "0" + String(minuto);
  filen += (sec > 9)     ? String(sec)     : "0" + String(sec);

  return direc + "/" + filen + ".RAW";
}

// ---------------------------------------------------------------
void printAddress(DeviceAddress deviceAddress) {
  for (uint8_t i = 0; i < 8; i++) {
    if (deviceAddress[i] < 16) dataFile.print("0");
    dataFile.print(deviceAddress[i], HEX);
  }
}

// ---------------------------------------------------------------
bool compareAddress(DeviceAddress addr1, DeviceAddress addr2) {
  for (int i = 0; i < 8; i++) {
    if (addr1[i] != addr2[i]) return false;
  }
  return true;
}

// ---------------------------------------------------------------
bool isLeapYear(int year) {
  return ((year % 4 == 0) && (year % 100 != 0)) || (year % 400 == 0);
}

const int daysInMonth[12] = {
  31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31
};

// ---------------------------------------------------------------
unsigned long convertToEpochTime(int segundo, int minuto, int hora, int dia, int mes, int ano) {
  const int EPOCH_YEAR = 1970;
  unsigned long seconds = 0;

  for (int y = EPOCH_YEAR; y < ano; y++)
    seconds += (isLeapYear(y) ? 366UL : 365UL) * 86400UL;

  for (int m = 1; m < mes; m++)
    seconds += ((m == 2 && isLeapYear(ano)) ? 29UL : daysInMonth[m - 1]) * 86400UL;

  seconds += (dia - 1) * 86400UL;
  seconds += hora * 3600UL + minuto * 60UL + segundo;
  return seconds;
}
