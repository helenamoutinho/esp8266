// ---------------------------------------------------------------
// Módulo Recetor
// Recebe dados do módulo de temperaturas a cada 10 minutos através do RX (serial)
// Liga o módulo esp8266 a cada 10 minutos e pode enviar dados através do TX (serial)
//
//  PINOS UTILIZADOS NO ARDUINO
//
//  D0  RX (serial)               – Recebe do XBee                    IN
//  D1  TX (serial)               – Envia para o esp8266              OUT
//  D2  Relé para desligar Arduino (bateria fraca)                    OUT
//  D3  Ativar LDO (3.3 V) do XBee (VCC do Arduino é insuficiente)    OUT
//  D4  Sleep do XBee                                                 OUT
//  D5  Liga ao GP0 do esp8266 para ver se há internet                IN
//  D6  Sleep do esp8266                                              OUT
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
//  VCC – alimentação do SD, RTC e do esp8266
// ---------------------------------------------------------------

#include <Wire.h>
#include <SD.h>

#define RTC_ADDRESS 0x68 

char x;
int volt;
float voltagem;
String fileName;
int minu;
int minu1;
int secu;
int secu1 = 0;
int hor;
int hor1;
int dia;
int mes;
int ano;

int flag;
int espConnected;

int vetor[5]; // Para guardar as variáveis recebidas pelos sensores

String value;
int nSensor;
int sValue;

void setup() {
    Serial.begin(9600);
    while (!Serial) {;}

    Wire.begin();
    Wire.setClock(400000L); // I2C em fast mode

    // Iniciar o cartão de dados
    pinMode(10, OUTPUT);
    if (!SD.begin(10)) { return; }

    // Configurar portas
    pinMode(2, OUTPUT);      // Para desligar Arduino
    digitalWrite(2, LOW);    // LOW - ligado

    pinMode(3, OUTPUT);      // LDO que liga ao XBEE
    digitalWrite(3, LOW);    // LOW - inicialmente desligado

    pinMode(4, OUTPUT);      // SLEEP do XBEE
    digitalWrite(4, LOW);    // LOW - fica sempre acordado, mas é o LDO que lhe dá corrente

    pinMode(5, INPUT);       // Ligado ao GP0 do esp8266

    pinMode(6, OUTPUT);      // Sleep do esp8266
    digitalWrite(6, LOW);    // Inicialmente desligado
}

void loop() {
    // Verificar o estado das baterias
    volt = 0;
    for (int k = 1; k <= 10; k++) { // Lê a porta analógica 10 vezes para fazer média 
        volt = volt + analogRead(A0);
        delay(1);
    }

    voltagem = (volt / 10.000 * 1.000 / 1023.000 * 3.300 * 2.000);
    if (voltagem < 3.5) {
        digitalWrite(2, HIGH); // Desliga tudo
    }

    // Criar ficheiro de dados
    fileName = getDirFilename();
    char fileNameChar[fileName.length() + 1];
    fileName.toCharArray(fileNameChar, sizeof(fileNameChar));
    File dataFile = SD.open(fileNameChar, FILE_WRITE);

    digitalWrite(3, HIGH); // Liga o XBEE no início de cada ciclo de 10 minutos

    do { // Gerar ciclos de 10 minutos
        // Leitura do tempo atual
        secu1 = secu;
        minu1 = minu;
        hor1  = hor;

        Wire.beginTransmission(RTC_ADDRESS);
        byte zero = 0x00;
        Wire.write(zero);
        Wire.endTransmission();
        Wire.requestFrom(RTC_ADDRESS, 3);
        secu = bcdToDec(Wire.read());
        minu = bcdToDec(Wire.read());
        hor  = bcdToDec(Wire.read());

        // Espera por dados dos XBEEs nos primeiros 2 minutos de cada ciclo
        if (minu == 0 || minu == 1 || minu == 10 || minu == 11 ||
            minu == 20 || minu == 21 || minu == 30 || minu == 31 ||
            minu == 40 || minu == 41 || minu == 50 || minu == 51) {

            if (Serial.available() > 0) {
                x = Serial.read(); // Lê a serial e se existirem dados grava no vetor (posição:nSensor; valor:sValue)

                if (x == 'a') {
                    value = "";
                } else if (x == 'b') {
                    nSensor = value.toInt();
                    value = "";
                } else if (x == 'c') {
                    sValue = value.toInt();
                    vetor[nSensor] = sValue;
                } else {
                    value = value + x;
                }
            }
        }

        // Na passagem do 2º minuto de cada ciclo
        if ((minu == 2 && minu1 == 1) || (minu == 12 && minu1 == 11) || 
            (minu == 22 && minu1 == 21) || (minu == 32 && minu1 == 31) || 
            (minu == 42 && minu1 == 41) || (minu == 52 && minu1 == 51)) {

            digitalWrite(3, LOW); // Desliga o XBEE 

            for (int i = 1; i <= 4; i++) { // Grava o vetor no ficheiro
                dataFile.print(i);
                dataFile.print('\t');
                dataFile.println(vetor[i]);
            }

            for (int i = 1; i <= 4; i++) { // Zera o vetor
                vetor[i] = 0;
            }

            dataFile.close();

            // Inicia a ligação ao esp8266
            digitalWrite(6, HIGH);  // Acorda o esp8266 e espera por rede      

            do { // Espera por rede
                espConnected = digitalRead(5);
                Wire.beginTransmission(RTC_ADDRESS);
                byte zero = 0x00;
                Wire.write(zero);
                Wire.endTransmission();
                Wire.requestFrom(RTC_ADDRESS, 3);
                secu = bcdToDec(Wire.read());
                minu = bcdToDec(Wire.read());
                hor  = bcdToDec(Wire.read());
            } while (espConnected == 0 || secu < 59);  // Até que exista rede ou se alcance o timeout (1 minuto)

            if (espConnected == 1) { // Se existe rede então envia o vetor atual para o esp8266
                for (int i = 1; i <= 4; i++) {
                    Serial.print('d');
                    Serial.print(nSensor);
                    Serial.print('e');
                    Serial.print(sValue);
                    Serial.print('f');
                }
            }

            delay(5000);          // Tempo necessário para que o esp8266 grave na base de dados (a definir melhor)
            digitalWrite(6, LOW); // Depois desliga o esp8266
        }

        // Fim do ciclo de 10 minutos
        if ((minu == 0 && minu1 == 59) || (minu == 10 && minu1 == 9) ||
            (minu == 20 && minu1 == 19) || (minu == 30 && minu1 == 29) ||
            (minu == 40 && minu1 == 39) || (minu == 50 && minu1 == 49)) {
            flag = 1;
        } else {
            flag = 0;
        }

    } while (flag == 0);
}

String getDirFilename() {
    Wire.beginTransmission(RTC_ADDRESS);
    byte zero = 0x00;
    Wire.write(zero);
    Wire.endTransmission();
    Wire.requestFrom(RTC_ADDRESS, 7);

    int sec   = bcdToDec(Wire.read());
    int minuto = bcdToDec(Wire.read());
    int hou   = bcdToDec(Wire.read() & 0b111111);
    int week  = bcdToDec(Wire.read());
    int montD = bcdToDec(Wire.read());
    int mont  = bcdToDec(Wire.read());
    int years = bcdToDec(Wire.read());

    String direc = String(years);
    if (mont > 9) direc = direc + String(mont);
    else direc = direc + "0" + String(mont);

    if (montD > 9) direc = direc + String(montD);
    else direc = direc + "0" + String(montD);

    char pathChar[direc.length() + 1];
    direc.toCharArray(pathChar, sizeof(pathChar));

    if (!SD.exists(pathChar)) {
        SD.mkdir(pathChar);
    }

    String filen = "";
    if (hou > 9) filen = String(hou);
    else filen = filen + "0" + String(hou);

    if (minuto > 9) filen = filen + String(minuto);
    else filen = filen + "0" + String(minuto);

    if (sec > 9) filen = filen + String(sec);
    else filen = filen + "0" + String(sec);

    String fileName = direc + "/" + filen + ".RAW";
    return fileName;
}

byte decToBcd(byte val) {
    return ((val / 10 * 16) + (val % 10));
}

byte bcdToDec(byte val) {
    return ((val / 16 * 10) + (val % 16));
}
