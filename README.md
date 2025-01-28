# PDFMAILER

## Using Dockerfile

#### Create container
``` 
docker build -t pdfmailer -f Dockerfile .
```
#### Run container
``` 
docker run --name PdfMailerContainer -it -d pdfmailer

```
#### Access container
``` 
docker exec -it PdfMailerContainer bash
```

### Run command
``` 
php bin/console app:sendPdf
```