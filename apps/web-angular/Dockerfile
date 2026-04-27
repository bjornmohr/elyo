FROM node:22

WORKDIR /app

# Install dependencies first for better caching
COPY package*.json ./
RUN npm install

# Copy the rest of the application
COPY . .

# Angular dev server runs on 4200 by default
EXPOSE 4200

# Start Angular dev server and bind to 0.0.0.0 for container access
CMD ["npm", "start", "--", "--host", "0.0.0.0", "--poll", "2000"]
