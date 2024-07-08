from django.http import JsonResponse
import serial
import time
from django.shortcuts import render

def send_sms(request):
    if request.method == 'POST':
        data = request.POST
        phone_number = data.get('phone')
        message = data.get('message')

        try:
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

            # Save the response to a .txt file
            with open('sms_response.txt', 'w') as file:
                file.write('SMS sent successfully')

            return JsonResponse({'status': 'success', 'message': 'SMS sent'})
        except Exception as e:
            return JsonResponse({'status': 'error', 'message': str(e)})

    # If request method is not POST, render the send_sms.html template
    return render(request, 'send_sms.html')

def sms_sent_success(request):
    # Read the SMS response from the .txt file
    with open('sms_response.txt', 'r') as file:
        sms_response = file.read()

    # Render the success.html template and pass the SMS response as context
    return render(request, 'success.html', {'sms_response': sms_response})
