from django.http import JsonResponse
import serial
import time
import datetime
import json
from django.shortcuts import render
from django.views.decorators.csrf import csrf_exempt

@csrf_exempt
def external_send_sms(request):
    if request.method == 'POST':
        try:
            data = json.loads(request.body.decode('utf-8'))
            phone_number = data.get('phone')
            message = data.get('message')
            send_sms(phone_number, message)
            return JsonResponse({'status': 'success'}, status=200)
        except Exception as e:
            return JsonResponse({'status': 'error', 'message': str(e)}, status=400)
    return JsonResponse({'status': 'method not allowed'}, status=405)

def send_sms(phone_number, message):
    try:
        # Attempt to send the SMS
        ser = serial.Serial('COM3', 9600, timeout=1)
        time.sleep(2)
        ser.write(b'AT\r')
        time.sleep(1)
        ser.write(b'AT+CMGF=1\r')
        time.sleep(1)
        ser.write(b'AT+CMGS="' + phone_number.encode() + b'"\r')
        time.sleep(1)
        ser.write(message.encode() + chr(26).encode())
        time.sleep(3)
        ser.close()

        # Log the successful message in a text file
        log_message = {
            "datetime": str(datetime.datetime.now()),
            "phone": phone_number,
            "message": message,
            "response": 'SMS sent successfully to {}'.format(phone_number)
        }
        log_message_str = json.dumps(log_message)

        with open('sms_logs.txt', 'a') as file:
            file.write(log_message_str + '\n')

        return log_message['response']

    except serial.SerialException as se:
        error_message = f"Serial port error: {se}"
        # Log the error message in a text file
        log_message = {
            "datetime": str(datetime.datetime.now()),
            "phone": phone_number,
            "message": message,
            "response": error_message
        }
        log_message_str = json.dumps(log_message)

        with open('sms_logs.txt', 'a') as file:
            file.write(log_message_str + '\n')

        return error_message

    except Exception as e:
        # If an exception occurs, log the error message in a text file
        error_message = str(e)
        log_message = {
            "datetime": str(datetime.datetime.now()),
            "phone": phone_number,
            "message": message,
            "response": error_message
        }
        log_message_str = json.dumps(log_message)

        with open('sms_logs.txt', 'a') as file:
            file.write(log_message_str + '\n')

        return error_message
